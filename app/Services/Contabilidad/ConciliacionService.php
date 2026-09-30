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
     * @return list<array{id: int, codigo: string, descripcion: string, id_moneda: ?int, moneda: ?array, saldo: string, id_cuenta_intereses: ?int, id_cuenta_ajuste: ?int}>
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
                'id_cuenta_intereses' => $cuenta->id_cuenta_intereses,
                'id_cuenta_ajuste' => $cuenta->id_cuenta_ajuste,
            ];
        })->values()->all();
    }

    /**
     * Prefill de asiento (sin guardar) para registrar la diferencia contra intereses o ajuste.
     *
     * @return array{fecha: string, descripcion: string, items: list<array>}
     */
    public function draftDiferencia(
        Carbon $fecha,
        int $idCuenta,
        string $saldoReal,
        string $tipo,
    ): array {
        if (! in_array($tipo, ['intereses', 'ajuste'], true)) {
            throw new RuntimeException('Tipo de asiento inválido.');
        }

        $monedaLocal = Moneda::local();
        if (! $monedaLocal) {
            throw new RuntimeException('No hay moneda local configurada.');
        }

        $cuenta = Cuenta::query()->with(['moneda', 'cuentaIntereses.moneda', 'cuentaAjuste.moneda'])->find($idCuenta);
        if (! $cuenta) {
            throw new RuntimeException('Cuenta no encontrada.');
        }

        $contraparte = $tipo === 'intereses' ? $cuenta->cuentaIntereses : $cuenta->cuentaAjuste;
        if (! $contraparte) {
            throw new RuntimeException(
                $tipo === 'intereses'
                    ? 'La cuenta no tiene cuenta de intereses asociada.'
                    : 'La cuenta no tiene cuenta de ajuste asociada.'
            );
        }

        $saldoReal = Money::round($saldoReal, 2);
        $saldos = $this->saldoService->getSaldos($cuenta, null, $fecha);
        $saldoSistema = $cuenta->id_moneda
            ? $saldos['saldo_origen']
            : $saldos['saldo'];

        $diferencia = Money::sub($saldoReal, $saldoSistema, 2);
        if (Money::isZero($diferencia)) {
            throw new RuntimeException('No hay diferencia para registrar.');
        }

        $cotizacion = $cuenta->moneda
            ? $this->cotizacionService->getRateForDate($cuenta->moneda, $fecha)
            : '1';
        $moneda = $cuenta->moneda ?? $monedaLocal;

        $debeOrigen = bccomp($diferencia, '0', 2) > 0 ? $diferencia : '0.00';
        $haberOrigen = bccomp($diferencia, '0', 2) < 0
            ? Money::round(bcmul($diferencia, '-1', 8), 2)
            : '0.00';

        $importeLocal = Money::round(Money::mul($diferencia, $cotizacion, 8), 2);
        $debeAjuste = bccomp($importeLocal, '0', 2) < 0
            ? Money::round(bcmul($importeLocal, '-1', 8), 2)
            : '0.00';
        $haberAjuste = bccomp($importeLocal, '0', 2) > 0 ? $importeLocal : '0.00';

        $ganado = bccomp($diferencia, '0', 2) > 0;
        if ($tipo === 'intereses') {
            $descripcion = ($ganado ? 'Intereses ganados' : 'Intereses perdidos').' — '.$cuenta->codigo.' '.$cuenta->descripcion;
        } else {
            $descripcion = 'Ajuste conciliación — '.$cuenta->codigo.' '.$cuenta->descripcion;
        }

        return [
            'fecha' => $fecha->toDateString(),
            'descripcion' => $descripcion,
            'items' => [
                [
                    'id_cuenta' => $cuenta->id,
                    'id_moneda' => $moneda->id,
                    'unidades' => null,
                    'debe_origen' => $debeOrigen,
                    'haber_origen' => $haberOrigen,
                    'cotizacion' => $cotizacion,
                ],
                [
                    'id_cuenta' => $contraparte->id,
                    'id_moneda' => $monedaLocal->id,
                    'unidades' => null,
                    'debe_origen' => $debeAjuste,
                    'haber_origen' => $haberAjuste,
                    'cotizacion' => '1',
                ],
            ],
        ];
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
