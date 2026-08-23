<?php

namespace App\Services\Contabilidad;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GastosService
{
    /**
     * Gastos por período × concepto (pagos), excluyendo origen clase TC.
     *
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   zoom: string,
     *   labels: list<string>,
     *   periodos: list<array{periodo: string, total: string, conceptos: list<array{cuenta: string, value: string, percent: float}>}>
     * }
     */
    public function report(Carbon $desde, Carbon $hasta, string $zoom = 'mensual'): array
    {
        $zoom = in_array($zoom, ['mensual', 'anual', 'fecha'], true) ? $zoom : 'mensual';
        $periodExpr = match ($zoom) {
            'anual' => "DATE_FORMAT(p.fecha, '%Y')",
            'fecha' => 'DATE(p.fecha)',
            default => "DATE_FORMAT(p.fecha, '%Y-%m')",
        };

        $rows = DB::select(
            "SELECT {$periodExpr} AS lb,
                    cc.descripcion AS cuenta,
                    ROUND(COALESCE(SUM(IF(COALESCE(co.clase, '') <> 'TC', p.importe * p.cotizacion, 0)), 0), 2) AS value
             FROM pagos AS p
             INNER JOIN cuentas AS cc ON cc.id = p.id_cuenta_concepto
             LEFT JOIN cuentas AS co ON co.id = p.id_cuenta_origen
             WHERE p.fecha BETWEEN ? AND ?
             GROUP BY lb, cuenta
             HAVING value <> 0
             ORDER BY lb, cuenta",
            [$desde->toDateString(), $hasta->toDateString()]
        );

        $byPeriod = [];
        $labelsSet = [];

        foreach ($rows as $row) {
            $periodo = (string) $row->lb;
            $cuenta = (string) $row->cuenta;
            $value = Money::round((string) $row->value, 2);
            $labelsSet[$cuenta] = true;
            $byPeriod[$periodo][$cuenta] = $value;
        }

        $labels = array_keys($labelsSet);
        sort($labels, SORT_STRING);

        $periodos = [];
        foreach ($byPeriod as $periodo => $conceptosMap) {
            $total = '0';
            foreach ($conceptosMap as $value) {
                $total = Money::add($total, $value, 2);
            }

            $conceptos = [];
            foreach ($conceptosMap as $cuenta => $value) {
                $percent = Money::isZero($total)
                    ? 0.0
                    : (float) Money::round(Money::mul(Money::div($value, $total, 8), '100', 8), 1);
                $conceptos[] = [
                    'cuenta' => $cuenta,
                    'value' => $value,
                    'percent' => $percent,
                ];
            }

            usort($conceptos, fn ($a, $b) => bccomp($b['value'], $a['value'], 2));

            $periodos[] = [
                'periodo' => $periodo,
                'total' => $total,
                'conceptos' => array_slice($conceptos, 0, 8),
            ];
        }

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'zoom' => $zoom,
            'labels' => $labels,
            'periodos' => $periodos,
        ];
    }
}
