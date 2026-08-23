<?php

namespace App\Services\Contabilidad;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VencimientosTcService
{
    /**
     * Cuotas de tarjeta pendientes de pago (hijos con id_origen), agrupadas por mes de vencimiento.
     *
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   meses: list<array{
     *     periodo: string,
     *     label: string,
     *     total: string,
     *     cantidad: int,
     *     detalle: list<array{tarjeta: string, codigo: string, total: string, cantidad: int}>
     *   }>,
     *   horizontes: list<array{
     *     meses: int,
     *     label: string,
     *     hasta: string,
     *     total: string,
     *     cantidad: int,
     *     detalle: list<array{tarjeta: string, codigo: string, total: string, cantidad: int}>
     *   }>,
     *   vencidos: array{total: string, cantidad: int},
     *   total_periodo: string
     * }
     */
    public function report(Carbon $desde, Carbon $hasta): array
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->endOfMonth()->startOfDay();

        $rows = DB::select(
            "SELECT DATE_FORMAT(p.fecha, '%Y-%m') AS periodo,
                    cc.codigo AS codigo,
                    cc.descripcion AS tarjeta,
                    COUNT(*) AS cantidad,
                    ROUND(COALESCE(SUM(p.importe * p.cotizacion), 0), 2) AS total
             FROM pagos AS p
             INNER JOIN cuentas AS cc ON cc.id = p.id_cuenta_concepto
             WHERE p.id_origen IS NOT NULL
               AND p.fecha BETWEEN ? AND ?
               AND (cc.clase = 'TC' OR cc.codigo LIKE '2.1.01.%')
             GROUP BY periodo, cc.codigo, cc.descripcion
             HAVING total <> 0
             ORDER BY periodo, cc.codigo",
            [$desde->toDateString(), $hasta->toDateString()]
        );

        $byPeriod = [];
        foreach ($rows as $row) {
            $periodo = (string) $row->periodo;
            $total = Money::round((string) $row->total, 2);
            $cantidad = (int) $row->cantidad;
            if (! isset($byPeriod[$periodo])) {
                $byPeriod[$periodo] = [
                    'periodo' => $periodo,
                    'total' => '0',
                    'cantidad' => 0,
                    'detalle' => [],
                ];
            }
            $byPeriod[$periodo]['total'] = Money::add($byPeriod[$periodo]['total'], $total, 2);
            $byPeriod[$periodo]['cantidad'] += $cantidad;
            $byPeriod[$periodo]['detalle'][] = [
                'tarjeta' => (string) $row->tarjeta,
                'codigo' => (string) $row->codigo,
                'total' => $total,
                'cantidad' => $cantidad,
            ];
        }

        $meses = [];
        $cursor = $desde->copy()->startOfMonth();
        $endMonth = $hasta->copy()->startOfMonth();
        $totalPeriodo = '0';

        while ($cursor->lte($endMonth)) {
            $periodo = $cursor->format('Y-m');
            $bucket = $byPeriod[$periodo] ?? [
                'periodo' => $periodo,
                'total' => '0',
                'cantidad' => 0,
                'detalle' => [],
            ];
            usort($bucket['detalle'], fn ($a, $b) => bccomp($b['total'], $a['total'], 2));
            $meses[] = [
                'periodo' => $periodo,
                'label' => $this->monthLabel($cursor),
                'total' => Money::round($bucket['total'], 2),
                'cantidad' => (int) $bucket['cantidad'],
                'detalle' => $bucket['detalle'],
            ];
            $totalPeriodo = Money::add($totalPeriodo, $bucket['total'], 2);
            $cursor->addMonth();
        }

        $horizontes = [];
        foreach ([3, 6, 9, 12] as $n) {
            $horizonHasta = $desde->copy()->addMonthsNoOverflow($n)->endOfMonth()->startOfDay();
            $agg = $this->detailBetween($desde, $horizonHasta);
            $horizontes[] = [
                'meses' => $n,
                'label' => "Próximos {$n} meses",
                'hasta' => $horizonHasta->toDateString(),
                'total' => $agg['total'],
                'cantidad' => $agg['cantidad'],
                'detalle' => $agg['detalle'],
            ];
        }

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'meses' => $meses,
            'horizontes' => $horizontes,
            'vencidos' => $this->aggregateBefore($desde),
            'total_periodo' => Money::round($totalPeriodo, 2),
        ];
    }

    /**
     * @return array{total: string, cantidad: int, detalle: list<array{tarjeta: string, codigo: string, total: string, cantidad: int}>}
     */
    private function detailBetween(Carbon $desde, Carbon $hasta): array
    {
        $rows = DB::select(
            "SELECT cc.codigo AS codigo,
                    cc.descripcion AS tarjeta,
                    COUNT(*) AS cantidad,
                    ROUND(COALESCE(SUM(p.importe * p.cotizacion), 0), 2) AS total
             FROM pagos AS p
             INNER JOIN cuentas AS cc ON cc.id = p.id_cuenta_concepto
             WHERE p.id_origen IS NOT NULL
               AND p.fecha BETWEEN ? AND ?
               AND (cc.clase = 'TC' OR cc.codigo LIKE '2.1.01.%')
             GROUP BY cc.codigo, cc.descripcion
             HAVING total <> 0
             ORDER BY total DESC",
            [$desde->toDateString(), $hasta->toDateString()]
        );

        $detalle = [];
        $total = '0';
        $cantidad = 0;
        foreach ($rows as $row) {
            $t = Money::round((string) $row->total, 2);
            $c = (int) $row->cantidad;
            $detalle[] = [
                'tarjeta' => (string) $row->tarjeta,
                'codigo' => (string) $row->codigo,
                'total' => $t,
                'cantidad' => $c,
            ];
            $total = Money::add($total, $t, 2);
            $cantidad += $c;
        }

        return [
            'total' => Money::round($total, 2),
            'cantidad' => $cantidad,
            'detalle' => $detalle,
        ];
    }

    /**
     * @return array{total: string, cantidad: int}
     */
    private function aggregateBefore(Carbon $desde): array
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS cantidad,
                    ROUND(COALESCE(SUM(p.importe * p.cotizacion), 0), 2) AS total
             FROM pagos AS p
             INNER JOIN cuentas AS cc ON cc.id = p.id_cuenta_concepto
             WHERE p.id_origen IS NOT NULL
               AND p.fecha < ?
               AND (cc.clase = 'TC' OR cc.codigo LIKE '2.1.01.%')",
            [$desde->toDateString()]
        );

        return [
            'total' => Money::round((string) ($row->total ?? 0), 2),
            'cantidad' => (int) ($row->cantidad ?? 0),
        ];
    }

    private function monthLabel(Carbon $month): string
    {
        $names = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        return ($names[$month->month] ?? $month->format('M')).' '.$month->year;
    }
}
