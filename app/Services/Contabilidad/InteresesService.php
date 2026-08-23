<?php

namespace App\Services\Contabilidad;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InteresesService
{
    public const DEFAULT_CUENTAS_ARS = ['4.3.20.01', '4.3.96.00'];

    public const DEFAULT_CUENTAS_USD = ['4.3.20.02', '4.3.20.00', '4.3.95.00'];

    /**
     * @param  list<string>|null  $cuentasArs
     * @param  list<string>|null  $cuentasUsd
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   zoom: string,
     *   cuentas_ars: list<string>,
     *   cuentas_usd: list<string>,
     *   filas: list<array>,
     *   totales: array
     * }
     */
    public function report(
        Carbon $desde,
        Carbon $hasta,
        ?array $cuentasArs = null,
        ?array $cuentasUsd = null,
    ): array {
        $cuentasArs = $cuentasArs ?: self::DEFAULT_CUENTAS_ARS;
        $cuentasUsd = $cuentasUsd ?: self::DEFAULT_CUENTAS_USD;

        $placeholdersArs = implode(',', array_fill(0, count($cuentasArs), '?'));
        $placeholdersUsd = implode(',', array_fill(0, count($cuentasUsd), '?'));
        $all = array_merge($cuentasArs, $cuentasUsd);
        $placeholdersAll = implode(',', array_fill(0, count($all), '?'));

        $usdId = (int) (DB::table('monedas')->where('codigo', 'USD')->value('id') ?? 2);

        $params = array_merge(
            [$desde->toDateString(), $hasta->toDateString()],
            $all,
            [$desde->toDateString(), $hasta->toDateString()],
            $cuentasArs,
            [$desde->toDateString(), $hasta->toDateString()],
            $cuentasUsd,
            [$desde->toDateString(), $hasta->toDateString()],
            $all,
        );

        $rows = DB::select(
            "SELECT
                CONCAT(LPAD(t.mes, 2, '0'), '/', t.anio) AS periodo,
                -1 * ROUND(COALESCE(p.saldo_pesos, 0), 2) AS valor_pesos,
                -1 * ROUND(COALESCE(d.saldo_dolares, 0), 2) AS valor_usd,
                -1 * ROUND(
                    COALESCE(d.saldo_dolares, 0) + COALESCE(p.saldo_pesos, 0) / NULLIF(c.cotizacion, 0),
                    2
                ) AS total_usd,
                -1 * ROUND(
                    COALESCE(d.saldo_dolares, 0) * COALESCE(c.cotizacion, 1) + COALESCE(p.saldo_pesos, 0),
                    2
                ) AS total_pesos
             FROM (
                SELECT YEAR(asi.fecha) AS anio, MONTH(asi.fecha) AS mes
                FROM asientos asi
                JOIN asiento_items ai ON asi.id = ai.id_asiento
                JOIN cuentas c ON ai.id_cuenta = c.id
                WHERE asi.fecha BETWEEN ? AND ?
                  AND c.codigo IN ({$placeholdersAll})
                GROUP BY anio, mes
             ) t
             LEFT JOIN (
                SELECT YEAR(asi.fecha) AS anio, MONTH(asi.fecha) AS mes,
                       SUM(ai.debe_origen - ai.haber_origen) AS saldo_pesos
                FROM asientos asi
                JOIN asiento_items ai ON asi.id = ai.id_asiento
                JOIN cuentas c ON ai.id_cuenta = c.id
                WHERE asi.fecha BETWEEN ? AND ?
                  AND c.codigo IN ({$placeholdersArs})
                GROUP BY anio, mes
             ) p ON p.anio = t.anio AND p.mes = t.mes
             LEFT JOIN (
                SELECT YEAR(asi.fecha) AS anio, MONTH(asi.fecha) AS mes,
                       SUM(ai.debe_origen - ai.haber_origen) AS saldo_dolares
                FROM asientos asi
                JOIN asiento_items ai ON asi.id = ai.id_asiento
                JOIN cuentas c ON ai.id_cuenta = c.id
                WHERE asi.fecha BETWEEN ? AND ?
                  AND c.codigo IN ({$placeholdersUsd})
                GROUP BY anio, mes
             ) d ON d.anio = t.anio AND d.mes = t.mes
             LEFT JOIN (
                SELECT fechas.anio, fechas.mes,
                       (
                         SELECT venta
                         FROM cotizaciones cot
                         WHERE cot.id_moneda = {$usdId}
                           AND cot.fecha <= LAST_DAY(STR_TO_DATE(CONCAT(fechas.anio, '-', fechas.mes, '-01'), '%Y-%m-%d'))
                         ORDER BY cot.fecha DESC
                         LIMIT 1
                       ) AS cotizacion
                FROM (
                    SELECT YEAR(asi.fecha) AS anio, MONTH(asi.fecha) AS mes
                    FROM asientos asi
                    JOIN asiento_items ai ON asi.id = ai.id_asiento
                    JOIN cuentas c ON ai.id_cuenta = c.id
                    WHERE asi.fecha BETWEEN ? AND ?
                      AND c.codigo IN ({$placeholdersAll})
                    GROUP BY anio, mes
                ) fechas
             ) c ON c.anio = t.anio AND c.mes = t.mes
             ORDER BY t.anio, t.mes",
            $params
        );

        $filas = [];
        $totales = [
            'valor_pesos' => '0',
            'valor_usd' => '0',
            'total_pesos' => '0',
            'total_usd' => '0',
        ];

        foreach ($rows as $row) {
            $fila = [
                'periodo' => (string) $row->periodo,
                'valor_pesos' => Money::round((string) ($row->valor_pesos ?? '0'), 2),
                'valor_usd' => Money::round((string) ($row->valor_usd ?? '0'), 2),
                'total_pesos' => Money::round((string) ($row->total_pesos ?? '0'), 2),
                'total_usd' => Money::round((string) ($row->total_usd ?? '0'), 2),
            ];
            $filas[] = $fila;
            foreach (array_keys($totales) as $key) {
                $totales[$key] = Money::add($totales[$key], $fila[$key], 2);
            }
        }

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'zoom' => 'mensual',
            'cuentas_ars' => $cuentasArs,
            'cuentas_usd' => $cuentasUsd,
            'filas' => $filas,
            'totales' => $totales,
        ];
    }
}
