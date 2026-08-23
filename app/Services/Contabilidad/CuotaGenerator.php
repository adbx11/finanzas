<?php

namespace App\Services\Contabilidad;

use App\Models\Cuenta;
use App\Models\Pago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CuotaGenerator
{
    private const CUENTA_PAGO_CODIGO = '1.1.01.01';

    public function __construct(
        private AsientoGenerator $asientoGenerator,
    ) {}

    public function regenerate(Pago $parent): void
    {
        $this->deleteChildren($parent);

        $cuotas = (int) ($parent->cuotas ?? 0);
        if ($cuotas <= 0) {
            return;
        }

        $parent->loadMissing(['cuentaOrigen', 'cuentaConcepto', 'moneda']);

        $cuentaPago = Cuenta::query()
            ->where('codigo', self::CUENTA_PAGO_CODIGO)
            ->firstOrFail();

        $importe = (string) $parent->importe;
        $positivo = bccomp($importe, '0', 8) > 0;
        $importeAbs = $positivo ? $importe : bcmul($importe, '-1', 2);
        $importeCuota = bcdiv($importeAbs, (string) $cuotas, 2);

        $importeRestante = $importeAbs;
        $fecha = Carbon::parse($parent->fecha)->day(15)->addMonth();

        DB::transaction(function () use (
            $parent,
            $cuotas,
            $cuentaPago,
            $positivo,
            $importeCuota,
            &$importeRestante,
            $fecha,
        ) {
            for ($cuota = 1; $cuota <= $cuotas; $cuota++) {
                $montoCuota = $importeCuota;
                if ($cuota === $cuotas) {
                    $montoCuota = $importeRestante;
                }

                $importeRestante = bcsub($importeRestante, $montoCuota, 2);
                $impte = $positivo ? $montoCuota : bcmul($montoCuota, '-1', 2);

                $pCuota = new Pago([
                    'fecha' => $fecha->copy(),
                    'id_cuenta_concepto' => $parent->id_cuenta_origen,
                    'id_cuenta_origen' => $cuentaPago->id,
                    'id_moneda' => $parent->id_moneda,
                    'importe' => $impte,
                    'cotizacion' => $parent->cotizacion,
                    'comentarios' => trim(($parent->comentarios ?? '')." (Cuota {$cuota} de {$cuotas})"),
                    'cuota' => $cuota,
                    'id_origen' => $parent->id,
                ]);
                $pCuota->save();

                $this->asientoGenerator->replaceForPago($pCuota);

                $fecha->addMonth();
            }
        });
    }

    public function deleteChildren(Pago $parent): void
    {
        $parent->loadMissing('cuotasHijas');

        foreach ($parent->cuotasHijas as $child) {
            $this->deletePago($child);
        }
    }

    public function deletePago(Pago $pago): void
    {
        if ($pago->id_asiento) {
            $this->asientoGenerator->deleteAsiento($pago->id_asiento);
        }

        if ($pago->cuotas && $pago->cuotas > 0) {
            $this->deleteChildren($pago);
        }

        $pago->delete();
    }
}
