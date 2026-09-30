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
     *   totales: array,
     *   totales_desglose: list<array>
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

        $desgloseByPeriodo = $this->desglosePorCuenta(
            $desde,
            $hasta,
            $cuentasArs,
            $cuentasUsd,
        );

        $filas = [];
        $totales = [
            'valor_pesos' => '0',
            'valor_usd' => '0',
            'total_pesos' => '0',
            'total_usd' => '0',
        ];
        $totalesDesglose = [];

        foreach ($rows as $row) {
            $periodo = (string) $row->periodo;
            $desglose = $desgloseByPeriodo[$periodo] ?? [];
            $fila = [
                'periodo' => $periodo,
                'valor_pesos' => Money::round((string) ($row->valor_pesos ?? '0'), 2),
                'valor_usd' => Money::round((string) ($row->valor_usd ?? '0'), 2),
                'total_pesos' => Money::round((string) ($row->total_pesos ?? '0'), 2),
                'total_usd' => Money::round((string) ($row->total_usd ?? '0'), 2),
                'desglose' => $desglose,
            ];
            $filas[] = $fila;
            foreach (array_keys($totales) as $key) {
                $totales[$key] = Money::add($totales[$key], $fila[$key], 2);
            }
            foreach ($desglose as $item) {
                $key = $item['codigo'];
                if (! isset($totalesDesglose[$key])) {
                    $totalesDesglose[$key] = [
                        'codigo' => $item['codigo'],
                        'nombre' => $item['nombre'],
                        'moneda' => $item['moneda'],
                        'importe' => '0',
                    ];
                }
                $totalesDesglose[$key]['importe'] = Money::add(
                    $totalesDesglose[$key]['importe'],
                    $item['importe'],
                    2,
                );
            }
        }

        usort($totalesDesglose, fn ($a, $b) => strcmp($a['codigo'], $b['codigo']));

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'zoom' => 'mensual',
            'cuentas_ars' => $cuentasArs,
            'cuentas_usd' => $cuentasUsd,
            'filas' => $filas,
            'totales' => $totales,
            'totales_desglose' => array_values($totalesDesglose),
        ];
    }

    /**
     * @param  list<string>  $cuentasArs
     * @param  list<string>  $cuentasUsd
     * @return array<string, list<array{codigo: string, nombre: string, moneda: string, importe: string}>>
     */
    private function desglosePorCuenta(
        Carbon $desde,
        Carbon $hasta,
        array $cuentasArs,
        array $cuentasUsd,
    ): array {
        $all = array_values(array_unique(array_merge($cuentasArs, $cuentasUsd)));
        if ($all === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($all), '?'));
        $arsSet = array_fill_keys($cuentasArs, true);

        $rows = DB::select(
            "SELECT
                CONCAT(LPAD(MONTH(asi.fecha), 2, '0'), '/', YEAR(asi.fecha)) AS periodo,
                c.codigo,
                c.nombre,
                -1 * ROUND(SUM(ai.debe_origen - ai.haber_origen), 2) AS importe
             FROM asientos asi
             JOIN asiento_items ai ON asi.id = ai.id_asiento
             JOIN cuentas c ON ai.id_cuenta = c.id
             WHERE asi.fecha BETWEEN ? AND ?
               AND c.codigo IN ({$placeholders})
             GROUP BY YEAR(asi.fecha), MONTH(asi.fecha), c.id, c.codigo, c.nombre
             HAVING importe <> 0
             ORDER BY YEAR(asi.fecha), MONTH(asi.fecha), c.codigo",
            array_merge([$desde->toDateString(), $hasta->toDateString()], $all),
        );

        $byPeriodo = [];
        foreach ($rows as $row) {
            $periodo = (string) $row->periodo;
            $codigo = (string) $row->codigo;
            $byPeriodo[$periodo][] = [
                'codigo' => $codigo,
                'nombre' => (string) ($row->nombre ?? ''),
                'moneda' => isset($arsSet[$codigo]) ? 'ARS' : 'USD',
                'importe' => Money::round((string) ($row->importe ?? '0'), 2),
            ];
        }

        return $byPeriodo;
    }
}
