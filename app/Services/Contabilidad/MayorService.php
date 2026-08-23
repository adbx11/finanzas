<?php

namespace App\Services\Contabilidad;

use App\Models\Cuenta;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MayorService
{
    /**
     * @return array{cuenta: array, desde: string, hasta: string, es_moneda_local: bool, saldo_anterior: array, movimientos: array, totales: array}
     */
    public function generar(Cuenta $cuenta, ?Carbon $desde, ?Carbon $hasta): array
    {
        $cuenta->loadMissing('moneda');

        $desde = $desde ?? Carbon::parse('1900-01-01');
        $hasta = $hasta ?? Carbon::parse('2900-01-01');

        $saldoAnterior = $this->saldoAnterior($cuenta->id, $desde);
        $movimientosRaw = $this->movimientos($cuenta->id, $desde, $hasta);

        $saldo = (string) $saldoAnterior['saldo'];
        $saldoOrigen = (string) $saldoAnterior['saldo_origen'];

        $totalDebe = '0';
        $totalHaber = '0';
        $totalDebeOrigen = '0';
        $totalHaberOrigen = '0';
        $totalUnidades = '0';

        $movimientos = [];

        foreach ($movimientosRaw as $row) {
            $debe = Money::round((string) $row->debe, 2);
            $haber = Money::round((string) $row->haber, 2);
            $debeOrigen = Money::round((string) $row->debe_origen, 2);
            $haberOrigen = Money::round((string) $row->haber_origen, 2);
            $unidades = (string) ($row->unidades ?? '0');

            $saldo = Money::add(Money::sub($saldo, $haber, 2), $debe, 2);
            $saldoOrigen = Money::add(Money::sub($saldoOrigen, $haberOrigen, 2), $debeOrigen, 2);

            $totalDebe = Money::add($totalDebe, $debe, 2);
            $totalHaber = Money::add($totalHaber, $haber, 2);
            $totalDebeOrigen = Money::add($totalDebeOrigen, $debeOrigen, 2);
            $totalHaberOrigen = Money::add($totalHaberOrigen, $haberOrigen, 2);
            $totalUnidades = Money::add($totalUnidades, $unidades, 2);

            $movimientos[] = [
                'id_asiento' => (int) $row->id,
                'fecha' => $row->fecha,
                'descripcion' => $row->descripcion,
                'unidades' => $unidades,
                'debe' => $debe,
                'haber' => $haber,
                'saldo' => $saldo,
                'cotizacion' => (string) $row->cotizacion,
                'debe_origen' => $debeOrigen,
                'haber_origen' => $haberOrigen,
                'saldo_origen' => $saldoOrigen,
                'simbolo' => $row->simbolo,
            ];
        }

        $esLocal = $cuenta->moneda?->local ?? true;

        return [
            'cuenta' => [
                'id' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'descripcion' => $cuenta->descripcion,
                'moneda' => $cuenta->moneda ? [
                    'id' => $cuenta->moneda->id,
                    'codigo' => $cuenta->moneda->codigo,
                    'simbolo' => $cuenta->moneda->simbolo,
                    'local' => (bool) $cuenta->moneda->local,
                ] : null,
            ],
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'es_moneda_local' => (bool) $esLocal,
            'saldo_anterior' => $saldoAnterior,
            'movimientos' => $movimientos,
            'totales' => [
                'unidades' => $totalUnidades,
                'debe' => $totalDebe,
                'haber' => $totalHaber,
                'saldo' => Money::sub($totalDebe, $totalHaber, 2),
                'debe_origen' => $totalDebeOrigen,
                'haber_origen' => $totalHaberOrigen,
                'saldo_origen' => Money::sub($totalDebeOrigen, $totalHaberOrigen, 2),
            ],
        ];
    }

    private function saldoAnterior(int $idCuenta, Carbon $desde): array
    {
        $row = DB::selectOne(
            'SELECT
                COALESCE(SUM(ai.debe - ai.haber), 0) AS saldo,
                COALESCE(SUM(ai.debe_origen - ai.haber_origen), 0) AS saldo_origen,
                COALESCE(SUM(COALESCE(ai.unidades, 0)), 0) AS unidades
             FROM asientos a
             INNER JOIN asiento_items ai ON a.id = ai.id_asiento AND ai.id_cuenta = ?
             WHERE a.fecha < ?',
            [$idCuenta, $desde->toDateString()]
        );

        return [
            'saldo' => Money::round((string) ($row->saldo ?? '0'), 2),
            'saldo_origen' => Money::round((string) ($row->saldo_origen ?? '0'), 2),
            'unidades' => (string) ($row->unidades ?? '0'),
        ];
    }

    private function movimientos(int $idCuenta, Carbon $desde, Carbon $hasta): array
    {
        return DB::select(
            'SELECT
                a.id,
                a.fecha,
                a.descripcion,
                ai.debe,
                ai.haber,
                ai.cotizacion,
                ai.debe_origen,
                ai.haber_origen,
                ai.unidades,
                mon.simbolo
             FROM asientos a
             INNER JOIN asiento_items ai ON a.id = ai.id_asiento AND ai.id_cuenta = ?
             INNER JOIN monedas mon ON mon.id = ai.id_moneda
             WHERE a.fecha BETWEEN ? AND ?
             ORDER BY a.fecha, a.id',
            [$idCuenta, $desde->toDateString(), $hasta->toDateString()]
        );
    }
}
