<?php

namespace App\Services\Contabilidad;

use App\Models\Asiento;
use App\Models\AsientoItem;
use App\Models\Cuenta;
use App\Models\Moneda;
use App\Services\CotizacionService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConciliacionService
{
    public const CUENTA_AJUSTE = '5.9.00.00';

    public function __construct(
        private CuentaSaldoService $saldoService,
        private CotizacionService $cotizacionService,
        private AsientoGenerator $asientoGenerator,
    ) {}

    /**
     * Cuentas imputables 1.1% con saldo al cierre de la fecha (como get.conciliacion legacy).
     *
     * @return list<array{id: int, codigo: string, descripcion: string, id_moneda: ?int, moneda: ?array, saldo: string}>
     */
    public function preview(Carbon $fecha): array
    {
        $cuentas = Cuenta::query()
            ->habilitadas()
            ->imputables()
            ->codigoLike('1.1')
            ->with('moneda')
            ->orderBy('codigo')
            ->get();

        return $cuentas->map(function (Cuenta $cuenta) use ($fecha) {
            $saldos = $this->saldoService->getSaldos($cuenta, null, $fecha);
            $saldo = $cuenta->id_moneda
                ? $saldos['saldo_origen']
                : $saldos['saldo'];

            return [
                'id' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'descripcion' => $cuenta->descripcion,
                'id_moneda' => $cuenta->id_moneda,
                'moneda' => $cuenta->moneda ? [
                    'id' => $cuenta->moneda->id,
                    'codigo' => $cuenta->moneda->codigo,
                    'simbolo' => $cuenta->moneda->simbolo,
                ] : null,
                'saldo' => $saldo,
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{id_cuenta: int, saldo_real: string}>  $lineas
     */
    public function save(Carbon $fecha, array $lineas): ?Asiento
    {
        $monedaLocal = Moneda::local();
        if (! $monedaLocal) {
            throw new RuntimeException('No hay moneda local configurada.');
        }

        $cuentaAjuste = Cuenta::query()->where('codigo', self::CUENTA_AJUSTE)->first();
        if (! $cuentaAjuste) {
            throw new RuntimeException('No existe la cuenta de ajustes '.self::CUENTA_AJUSTE.'.');
        }

        $items = [];
        $importeAjuste = '0';

        foreach ($lineas as $linea) {
            $cuenta = Cuenta::query()->with('moneda')->find($linea['id_cuenta']);
            if (! $cuenta) {
                continue;
            }

            $saldoReal = Money::round((string) $linea['saldo_real'], 2);
            $saldos = $this->saldoService->getSaldos($cuenta, null, $fecha);
            $saldoSistema = $cuenta->id_moneda
                ? $saldos['saldo_origen']
                : $saldos['saldo'];

            $diferencia = Money::sub($saldoReal, $saldoSistema, 2);
            if (Money::isZero($diferencia)) {
                continue;
            }

            $cotizacion = $cuenta->moneda
                ? $this->cotizacionService->getRateForDate($cuenta->moneda, $fecha)
                : '1';
            $moneda = $cuenta->moneda ?? $monedaLocal;

            $debeOrigen = bccomp($diferencia, '0', 2) > 0 ? $diferencia : '0.00';
            $haberOrigen = bccomp($diferencia, '0', 2) < 0
                ? Money::round(bcmul($diferencia, '-1', 8), 2)
                : '0.00';

            $item = new AsientoItem([
                'id_cuenta' => $cuenta->id,
                'id_moneda' => $moneda->id,
                'debe_origen' => $debeOrigen,
                'haber_origen' => $haberOrigen,
                'cotizacion' => $cotizacion,
            ]);
            $this->asientoGenerator->applyLocalAmounts($item);
            $items[] = $item;

            $importeAjuste = Money::add(
                $importeAjuste,
                Money::round(Money::mul($diferencia, $cotizacion, 8), 2),
                2
            );
        }

        if (Money::isZero($importeAjuste) || $items === []) {
            return null;
        }

        $debeAjuste = bccomp($importeAjuste, '0', 2) < 0
            ? Money::round(bcmul($importeAjuste, '-1', 8), 2)
            : '0.00';
        $haberAjuste = bccomp($importeAjuste, '0', 2) > 0 ? $importeAjuste : '0.00';

        $itemAjuste = new AsientoItem([
            'id_cuenta' => $cuentaAjuste->id,
            'id_moneda' => $monedaLocal->id,
            'debe_origen' => $debeAjuste,
            'haber_origen' => $haberAjuste,
            'cotizacion' => '1',
        ]);
        $this->asientoGenerator->applyLocalAmounts($itemAjuste);
        $items[] = $itemAjuste;

        return DB::transaction(function () use ($fecha, $items) {
            $asiento = new Asiento([
                'fecha' => $fecha->toDateString(),
                'descripcion' => 'Conciliacion',
                'ejercicio' => (int) $fecha->format('Y'),
                'confirmado' => true,
                'fecha_creacion' => now(),
                'fecha_confirmacion' => now(),
            ]);
            $asiento->save();

            foreach ($items as $item) {
                $item->id_asiento = $asiento->id;
                $item->save();
            }

            return $asiento->load('items');
        });
    }
}
