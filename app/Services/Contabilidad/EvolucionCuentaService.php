<?php

namespace App\Services\Contabilidad;

use App\Models\Cuenta;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EvolucionCuentaService
{
    /**
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   zoom: string,
     *   cuenta: array{id: int, codigo: string, descripcion: string, moneda: ?string},
     *   moneda: string,
     *   data_valor: list<array{y: string, a: string}>,
     *   data_variacion: list<array{y: string, a: string, debe: string, haber: string}>,
     *   data_variacion_acumulada: list<array{y: string, a: string}>
     * }
     */
    public function report(Cuenta $cuenta, Carbon $desde, Carbon $hasta, string $zoom = 'mensual'): array
    {
        $zoom = in_array($zoom, ['mensual', 'anual', 'fecha'], true) ? $zoom : 'mensual';
        $cuenta->loadMissing('moneda');

        $useOrigen = $cuenta->id_moneda !== null;
        $saldoAnt = $this->saldoAnterior($cuenta->id, $desde, $useOrigen);
        $byPeriod = $this->indexByPeriod($this->evolucion($cuenta->id, $desde, $hasta, $zoom, $useOrigen));
        $periodos = $this->periodLabels($desde, $hasta, $zoom);

        $acumulado = $saldoAnt;
        $varPctAnterior = '0';

        $dataValor = [];
        $dataVariacion = [];
        $dataVariacionAcumulada = [];

        foreach ($periodos as $lb) {
            $row = $byPeriod[$lb] ?? ['value' => '0', 'debe' => '0', 'haber' => '0'];
            $valorPeriodo = Money::round((string) $row['value'], 2);
            $anterior = $acumulado;
            $acumulado = Money::add($acumulado, $valorPeriodo, 2);

            $varPct = Money::isZero($anterior)
                ? '0'
                : Money::round(
                    Money::mul(
                        Money::sub(Money::div($acumulado, $anterior, 8), '1', 8),
                        '100',
                        8
                    ),
                    4
                );
            $varPctAcum = Money::add($varPct, $varPctAnterior, 4);
            $varPctAnterior = $varPctAcum;

            $dataValor[] = ['y' => $lb, 'a' => $acumulado];
            $dataVariacion[] = [
                'y' => $lb,
                'a' => $valorPeriodo,
                'debe' => Money::round((string) $row['debe'], 2),
                'haber' => Money::round((string) $row['haber'], 2),
            ];
            $dataVariacionAcumulada[] = ['y' => $lb, 'a' => $varPctAcum];
        }

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'zoom' => $zoom,
            'cuenta' => [
                'id' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'descripcion' => $cuenta->descripcion,
                'moneda' => $cuenta->moneda?->codigo,
            ],
            'moneda' => $cuenta->moneda?->simbolo ?? '$',
            'data_valor' => $dataValor,
            'data_variacion' => $dataVariacion,
            'data_variacion_acumulada' => $dataVariacionAcumulada,
        ];
    }

    private function saldoAnterior(int $idCuenta, Carbon $desde, bool $useOrigen): string
    {
        $col = $useOrigen
            ? 'SUM(ai.debe_origen - ai.haber_origen)'
            : 'SUM(ai.debe - ai.haber)';

        $row = DB::selectOne(
            "SELECT COALESCE({$col}, 0) AS saldo
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON a.id = ai.id_asiento AND ai.id_cuenta = ?
             WHERE a.fecha < ?",
            [$idCuenta, $desde->toDateString()]
        );

        return Money::round((string) ($row->saldo ?? 0), 2);
    }

    /**
     * @return list<array{lb: string, value: string, debe: string, haber: string}>
     */
    private function evolucion(int $idCuenta, Carbon $desde, Carbon $hasta, string $zoom, bool $useOrigen): array
    {
        $periodExpr = match ($zoom) {
            'anual' => "DATE_FORMAT(a.fecha, '%Y')",
            'fecha' => 'DATE(a.fecha)',
            default => "DATE_FORMAT(a.fecha, '%Y-%m')",
        };

        $valueExpr = $useOrigen
            ? 'SUM(ai.debe_origen - ai.haber_origen)'
            : 'SUM(ai.debe - ai.haber)';
        $debeExpr = $useOrigen ? 'SUM(ai.debe_origen)' : 'SUM(ai.debe)';
        $haberExpr = $useOrigen ? 'SUM(ai.haber_origen)' : 'SUM(ai.haber)';

        $rows = DB::select(
            "SELECT {$periodExpr} AS lb,
                    COALESCE({$valueExpr}, 0) AS value,
                    COALESCE({$debeExpr}, 0) AS debe,
                    COALESCE({$haberExpr}, 0) AS haber
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON a.id = ai.id_asiento AND ai.id_cuenta = ?
             WHERE a.fecha BETWEEN ? AND ?
             GROUP BY lb
             ORDER BY lb",
            [$idCuenta, $desde->toDateString(), $hasta->toDateString()]
        );

        return array_map(fn ($r) => [
            'lb' => (string) $r->lb,
            'value' => (string) $r->value,
            'debe' => (string) $r->debe,
            'haber' => (string) $r->haber,
        ], $rows);
    }

    /**
     * @param  list<array{lb: string, value: string, debe: string, haber: string}>  $rows
     * @return array<string, array{value: string, debe: string, haber: string}>
     */
    private function indexByPeriod(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['lb']] = $row;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function periodLabels(Carbon $desde, Carbon $hasta, string $zoom): array
    {
        $labels = [];
        $cursor = $desde->copy()->startOfDay();
        $end = $hasta->copy()->startOfDay();

        if ($zoom === 'anual') {
            $cursor = $cursor->startOfYear();
            while ($cursor->year <= $end->year) {
                $labels[] = (string) $cursor->year;
                $cursor->addYear();
            }
        } elseif ($zoom === 'fecha') {
            while ($cursor->lte($end)) {
                $labels[] = $cursor->toDateString();
                $cursor->addDay();
            }
        } else {
            $cursor = $cursor->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();
            while ($cursor->lte($endMonth)) {
                $labels[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }
        }

        return $labels;
    }
}
