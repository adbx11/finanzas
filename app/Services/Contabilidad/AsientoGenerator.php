<?php

namespace App\Services\Contabilidad;

use App\Models\Asiento;
use App\Models\AsientoItem;
use App\Models\Ingreso;
use App\Models\Moneda;
use App\Models\Pago;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class AsientoGenerator
{
    public function fromIngreso(Ingreso $ingreso): Asiento
    {
        $ingreso->loadMissing(['cuentaDestino', 'cuentaConcepto', 'moneda']);

        $asiento = new Asiento([
            'fecha' => $ingreso->fecha,
            'descripcion' => 'Ingreso: '.$ingreso->cuentaConcepto->descripcion
                .($ingreso->comentarios ? ' - '.$ingreso->comentarios : ''),
            'confirmado' => true,
            'fecha_creacion' => now(),
            'fecha_confirmacion' => now(),
            'ejercicio' => (int) $ingreso->fecha->format('Y'),
        ]);

        $itemDestino = new AsientoItem([
            'id_cuenta' => $ingreso->id_cuenta_destino,
            'id_moneda' => $ingreso->id_moneda,
            'debe_origen' => $ingreso->importe,
            'haber_origen' => '0',
            'cotizacion' => $ingreso->cotizacion,
        ]);

        $itemConcepto = new AsientoItem([
            'id_cuenta' => $ingreso->id_cuenta_concepto,
            'id_moneda' => $ingreso->id_moneda,
            'debe_origen' => '0',
            'haber_origen' => $ingreso->importe,
            'cotizacion' => $ingreso->cotizacion,
        ]);

        $this->applyLocalAmounts($itemDestino);
        $this->applyLocalAmounts($itemConcepto);

        $asiento->setRelation('items', collect([$itemDestino, $itemConcepto]));

        return $asiento;
    }

    public function fromPago(Pago $pago): Asiento
    {
        $pago->loadMissing(['cuentaOrigen', 'cuentaConcepto', 'moneda']);

        $monedaLocal = ($pago->moneda && $pago->moneda->local)
            ? $pago->moneda
            : Moneda::local();
        $importe = (string) $pago->importe;
        $cotizacion = (string) $pago->cotizacion;
        $importeMl = Money::round(Money::mul($importe, $cotizacion, 8), 2);
        $positivo = Money::isPositive($importe);

        $asiento = new Asiento([
            'fecha' => $pago->fecha,
            'descripcion' => 'Pago: '.$pago->cuentaConcepto->descripcion
                .($pago->comentarios ? ' - '.$pago->comentarios : ''),
            'confirmado' => true,
            'fecha_creacion' => now(),
            'fecha_confirmacion' => now(),
            'ejercicio' => (int) $pago->fecha->format('Y'),
            'cuota' => $pago->cuota,
        ]);

        $items = [];

        if ($pago->cuota === null) {
            $itemConcepto = new AsientoItem([
                'id_cuenta' => $pago->id_cuenta_concepto,
                'id_moneda' => $monedaLocal?->id,
                'cotizacion' => '1',
            ]);
            $itemOrigen = new AsientoItem([
                'id_cuenta' => $pago->id_cuenta_origen,
                'id_moneda' => $pago->id_moneda,
                'cotizacion' => $cotizacion,
            ]);

            if ($positivo) {
                $itemConcepto->debe_origen = $importeMl;
                $itemConcepto->haber_origen = '0';
                $itemOrigen->debe_origen = '0';
                $itemOrigen->haber_origen = $importe;
            } else {
                $abs = ltrim($importe, '-');
                $absMl = ltrim($importeMl, '-');
                $itemConcepto->debe_origen = '0';
                $itemConcepto->haber_origen = $absMl;
                $itemOrigen->debe_origen = $abs;
                $itemOrigen->haber_origen = '0';
            }

            $items = [$itemConcepto, $itemOrigen];
        } else {
            $itemConcepto = new AsientoItem([
                'id_cuenta' => $pago->id_cuenta_concepto,
                'id_moneda' => $pago->id_moneda,
                'cotizacion' => $cotizacion,
            ]);
            $itemOrigen = new AsientoItem([
                'id_cuenta' => $pago->id_cuenta_origen,
                'id_moneda' => $monedaLocal?->id,
                'cotizacion' => '1',
            ]);

            if ($positivo) {
                $itemConcepto->debe_origen = $importe;
                $itemConcepto->haber_origen = '0';
                $itemOrigen->debe_origen = '0';
                $itemOrigen->haber_origen = $importeMl;
            } else {
                $abs = ltrim($importe, '-');
                $absMl = ltrim($importeMl, '-');
                $itemConcepto->debe_origen = '0';
                $itemConcepto->haber_origen = $abs;
                $itemOrigen->debe_origen = $absMl;
                $itemOrigen->haber_origen = '0';
            }

            $items = [$itemConcepto, $itemOrigen];
        }

        foreach ($items as $item) {
            $this->applyLocalAmounts($item);
        }

        $asiento->setRelation('items', collect($items));

        return $asiento;
    }

    public function persist(Asiento $asiento): Asiento
    {
        return DB::transaction(function () use ($asiento) {
            $asiento->save();

            foreach ($asiento->items as $item) {
                $item->id_asiento = $asiento->id;
                $item->save();
            }

            return $asiento->load('items');
        });
    }

    public function replaceForIngreso(Ingreso $ingreso): Asiento
    {
        if ($ingreso->id_asiento) {
            $this->deleteAsiento($ingreso->id_asiento);
            $ingreso->id_asiento = null;
        }

        $asiento = $this->fromIngreso($ingreso);
        $this->persist($asiento);
        $ingreso->id_asiento = $asiento->id;
        $ingreso->save();

        return $asiento;
    }

    public function replaceForPago(Pago $pago): Asiento
    {
        if ($pago->id_asiento) {
            $this->deleteAsiento($pago->id_asiento);
            $pago->id_asiento = null;
        }

        $asiento = $this->fromPago($pago);
        $this->persist($asiento);
        $pago->id_asiento = $asiento->id;
        $pago->save();

        return $asiento;
    }

    public function deleteAsiento(int $asientoId): void
    {
        DB::transaction(function () use ($asientoId) {
            AsientoItem::query()->where('id_asiento', $asientoId)->delete();
            Asiento::query()->where('id', $asientoId)->delete();
        });
    }

    public function applyLocalAmounts(AsientoItem $item): void
    {
        $cotizacion = (string) ($item->cotizacion ?? '1');
        $debeOrigen = (string) ($item->debe_origen ?? '0');
        $haberOrigen = (string) ($item->haber_origen ?? '0');

        $item->debe = Money::round(Money::mul($debeOrigen, $cotizacion, 8), 2);
        $item->haber = Money::round(Money::mul($haberOrigen, $cotizacion, 8), 2);
    }

    public function deleteAsientoCascade(int $asientoId): void
    {
        DB::transaction(function () use ($asientoId) {
            $hijos = Asiento::query()->where('id_origen', $asientoId)->pluck('id');
            foreach ($hijos as $hijoId) {
                $this->deleteAsientoCascade((int) $hijoId);
            }
            $this->deleteAsiento($asientoId);
        });
    }
}
