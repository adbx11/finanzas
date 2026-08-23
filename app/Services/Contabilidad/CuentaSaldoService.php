<?php

namespace App\Services\Contabilidad;

use App\Models\Cuenta;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CuentaSaldoService
{
    /**
     * @return array{saldo: string, saldo_origen: string, unidades: string, por_moneda: array<int, array{id_moneda: int, saldo: string}>}
     */
    public function getSaldos(Cuenta $cuenta, ?Carbon $desde, ?Carbon $hasta): array
    {
        $bindings = [$cuenta->id];
        $fechaSql = '';

        if ($desde) {
            $fechaSql .= ' AND a.fecha >= ?';
            $bindings[] = $desde->toDateString();
        }

        if ($hasta) {
            $fechaSql .= ' AND a.fecha <= ?';
            $bindings[] = $hasta->toDateString();
        }

        $row = DB::selectOne(
            "SELECT
                COALESCE(SUM(ai.debe - ai.haber), 0) AS saldo,
                COALESCE(SUM(ai.debe_origen - ai.haber_origen), 0) AS saldo_origen,
                COALESCE(SUM(COALESCE(ai.unidades, 0)), 0) AS unidades
             FROM asientos a
             INNER JOIN asiento_items ai ON ai.id_asiento = a.id AND ai.id_cuenta = ?
             WHERE 1=1 {$fechaSql}",
            $bindings
        );

        $porMoneda = DB::select(
            "SELECT
                ai.id_moneda,
                COALESCE(SUM(ai.debe_origen - ai.haber_origen), 0) AS saldo
             FROM asientos a
             INNER JOIN asiento_items ai ON ai.id_asiento = a.id AND ai.id_cuenta = ?
             WHERE ai.id_moneda IS NOT NULL {$fechaSql}
             GROUP BY ai.id_moneda",
            $bindings
        );

        return [
            'saldo' => Money::round((string) ($row->saldo ?? '0'), 2),
            'saldo_origen' => Money::round((string) ($row->saldo_origen ?? '0'), 2),
            'unidades' => (string) ($row->unidades ?? '0'),
            'por_moneda' => collect($porMoneda)->map(fn ($m) => [
                'id_moneda' => (int) $m->id_moneda,
                'saldo' => Money::round((string) $m->saldo, 2),
            ])->all(),
        ];
    }

    public function addSaldos(array $a, array $b): array
    {
        $porMoneda = [];

        foreach (array_merge($a['por_moneda'] ?? [], $b['por_moneda'] ?? []) as $item) {
            $id = (int) $item['id_moneda'];
            $porMoneda[$id] = Money::add($porMoneda[$id] ?? '0', (string) $item['saldo'], 2);
        }

        return [
            'saldo' => Money::add((string) ($a['saldo'] ?? '0'), (string) ($b['saldo'] ?? '0'), 2),
            'saldo_origen' => Money::add((string) ($a['saldo_origen'] ?? '0'), (string) ($b['saldo_origen'] ?? '0'), 2),
            'unidades' => Money::add((string) ($a['unidades'] ?? '0'), (string) ($b['unidades'] ?? '0'), 2),
            'por_moneda' => collect($porMoneda)->map(fn ($saldo, $id) => [
                'id_moneda' => (int) $id,
                'saldo' => $saldo,
            ])->values()->all(),
        ];
    }
}
