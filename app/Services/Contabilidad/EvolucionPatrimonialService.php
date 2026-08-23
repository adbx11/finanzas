<?php

namespace App\Services\Contabilidad;

use App\Services\CotizacionService;
use App\Support\ValorMultimoneda;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EvolucionPatrimonialService
{
    public function __construct(
        private CotizacionService $cotizacionService,
    ) {}

    /**
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   zoom: string,
     *   data_valor: list<array>,
     *   data_variacion: list<array>,
     *   data_variacion_acumulada: list<array>
     * }
     */
    public function report(Carbon $desde, Carbon $hasta, string $zoom = 'mensual'): array
    {
        $zoom = in_array($zoom, ['mensual', 'anual', 'fecha'], true) ? $zoom : 'mensual';
        $periodos = $this->periodLabels($desde, $hasta, $zoom);

        $rowsA = $this->indexByPeriod($this->evolucionPorTipo($desde, $hasta, $zoom, 'A'));
        $rowsP = $this->indexByPeriod($this->evolucionPorTipo($desde, $hasta, $zoom, 'P'));
        $rowsIng = $this->indexByPeriod($this->evolucionPorCodigo($desde, $hasta, $zoom, '4.%'));
        $rowsEgr = $this->indexByPeriod($this->evolucionPorCodigo($desde, $hasta, $zoom, '5.%'));

        $acumA = ValorMultimoneda::fromRow(
            $this->cotizacionService,
            $this->saldoAnteriorPorTipo($desde, 'A'),
            $desde
        );
        $acumP = ValorMultimoneda::fromRow(
            $this->cotizacionService,
            $this->saldoAnteriorPorTipo($desde, 'P'),
            $desde
        );
        $acumP->multiply('-1');

        $varPctAntA = ValorMultimoneda::zero($this->cotizacionService);
        $varPctAntP = ValorMultimoneda::zero($this->cotizacionService);

        $dataValor = [];
        $dataVariacion = [];
        $dataVariacionAcumulada = [];

        foreach ($periodos as $lb) {
            $fechaCot = $this->fechaCotizacion($lb, $zoom);
            $empty = ['ars' => 0, 'usd' => 0, 'eur' => 0, 'btc' => 0];

            $periodoA = ValorMultimoneda::fromRow($this->cotizacionService, $rowsA[$lb] ?? $empty, $fechaCot);
            $periodoP = ValorMultimoneda::fromRow($this->cotizacionService, $rowsP[$lb] ?? $empty, $fechaCot);
            $ingresos = ValorMultimoneda::fromRow($this->cotizacionService, $rowsIng[$lb] ?? $empty, $fechaCot);
            $egresos = ValorMultimoneda::fromRow($this->cotizacionService, $rowsEgr[$lb] ?? $empty, $fechaCot);

            $periodoP->multiply('-1');
            $ingresos->multiply('-1');

            // Clone BEFORE add (fix legacy bug that always yielded ~0%).
            $anteriorA = $acumA->clonar();
            $anteriorP = $acumP->clonar();

            $acumA->add($periodoA, $fechaCot);
            $acumP->add($periodoP, $fechaCot);

            $varPctA = $acumA->variacionPorcentual($anteriorA);
            $varPctP = $acumP->variacionPorcentual($anteriorP);

            $varPctAcumA = $varPctA->clonar();
            $varPctAcumA->add($varPctAntA, $fechaCot);
            $varPctAntA = $varPctAcumA;

            $varPctAcumP = $varPctP->clonar();
            $varPctAcumP->add($varPctAntP, $fechaCot);
            $varPctAntP = $varPctAcumP;

            $dif = $egresos->clonar();
            $dif->multiply('-1');
            $dif->add($ingresos, $fechaCot);

            $dataValor[] = array_merge(['y' => $lb], $acumA->toPrefixedArray('a_'), $acumP->toPrefixedArray('p_'));
            $dataVariacion[] = array_merge(
                ['y' => $lb],
                $periodoA->toPrefixedArray('a_'),
                $periodoP->toPrefixedArray('p_'),
                $ingresos->toPrefixedArray('ingresos_'),
                $egresos->toPrefixedArray('egresos_'),
                $dif->toPrefixedArray('dif_')
            );
            $dataVariacionAcumulada[] = array_merge(
                ['y' => $lb],
                $varPctAcumA->toPrefixedArray('a_'),
                $varPctAcumP->toPrefixedArray('p_')
            );
        }

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'zoom' => $zoom,
            'data_valor' => $dataValor,
            'data_variacion' => $dataVariacion,
            'data_variacion_acumulada' => $dataVariacionAcumulada,
        ];
    }

    /**
     * @return array{ars: string, usd: string, eur: string, btc: string}
     */
    private function saldoAnteriorPorTipo(Carbon $desde, string $tipo): array
    {
        $row = DB::selectOne(
            "SELECT
                COALESCE(SUM(IF(mon.codigo = 'ARS', ai.debe_origen - ai.haber_origen, 0)), 0) AS ars,
                COALESCE(SUM(IF(mon.codigo = 'USD', ai.debe_origen - ai.haber_origen, 0)), 0) AS usd,
                COALESCE(SUM(IF(mon.codigo = 'EUR', ai.debe_origen - ai.haber_origen, 0)), 0) AS eur,
                COALESCE(SUM(IF(mon.codigo = 'BTC', ai.debe_origen - ai.haber_origen, 0)), 0) AS btc
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON a.id = ai.id_asiento
             INNER JOIN cuentas AS cta ON cta.id = ai.id_cuenta AND cta.tipo_cuenta = ?
             INNER JOIN monedas AS mon ON mon.id = ai.id_moneda
             WHERE a.fecha < ?",
            [$tipo, $desde->toDateString()]
        );

        return [
            'ars' => (string) ($row->ars ?? 0),
            'usd' => (string) ($row->usd ?? 0),
            'eur' => (string) ($row->eur ?? 0),
            'btc' => (string) ($row->btc ?? 0),
        ];
    }

    /**
     * @return list<array{lb: string, ars: string, usd: string, eur: string, btc: string}>
     */
    private function evolucionPorTipo(Carbon $desde, Carbon $hasta, string $zoom, string $tipo): array
    {
        $periodExpr = $this->periodSqlExpr('a.fecha', $zoom);

        $rows = DB::select(
            "SELECT {$periodExpr} AS lb,
                    COALESCE(SUM(IF(mon.codigo = 'ARS', ai.debe_origen - ai.haber_origen, 0)), 0) AS ars,
                    COALESCE(SUM(IF(mon.codigo = 'USD', ai.debe_origen - ai.haber_origen, 0)), 0) AS usd,
                    COALESCE(SUM(IF(mon.codigo = 'EUR', ai.debe_origen - ai.haber_origen, 0)), 0) AS eur,
                    COALESCE(SUM(IF(mon.codigo = 'BTC', ai.debe_origen - ai.haber_origen, 0)), 0) AS btc
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON a.id = ai.id_asiento
             INNER JOIN cuentas AS c ON c.id = ai.id_cuenta AND c.tipo_cuenta = ?
             INNER JOIN monedas AS mon ON mon.id = ai.id_moneda
             WHERE a.fecha BETWEEN ? AND ?
             GROUP BY lb
             ORDER BY lb",
            [$tipo, $desde->toDateString(), $hasta->toDateString()]
        );

        return array_map(fn ($r) => [
            'lb' => (string) $r->lb,
            'ars' => (string) $r->ars,
            'usd' => (string) $r->usd,
            'eur' => (string) $r->eur,
            'btc' => (string) $r->btc,
        ], $rows);
    }

    /**
     * @return list<array{lb: string, ars: string, usd: string, eur: string, btc: string}>
     */
    private function evolucionPorCodigo(Carbon $desde, Carbon $hasta, string $zoom, string $like): array
    {
        $periodExpr = $this->periodSqlExpr('a.fecha', $zoom);

        $rows = DB::select(
            "SELECT {$periodExpr} AS lb,
                    COALESCE(SUM(IF(mon.codigo = 'ARS', ai.debe_origen - ai.haber_origen, 0)), 0) AS ars,
                    COALESCE(SUM(IF(mon.codigo = 'USD', ai.debe_origen - ai.haber_origen, 0)), 0) AS usd,
                    COALESCE(SUM(IF(mon.codigo = 'EUR', ai.debe_origen - ai.haber_origen, 0)), 0) AS eur,
                    COALESCE(SUM(IF(mon.codigo = 'BTC', ai.debe_origen - ai.haber_origen, 0)), 0) AS btc
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON a.id = ai.id_asiento
             INNER JOIN cuentas AS c ON c.id = ai.id_cuenta AND c.codigo LIKE ?
             INNER JOIN monedas AS mon ON mon.id = ai.id_moneda
             WHERE a.fecha BETWEEN ? AND ?
             GROUP BY lb
             ORDER BY lb",
            [$like, $desde->toDateString(), $hasta->toDateString()]
        );

        return array_map(fn ($r) => [
            'lb' => (string) $r->lb,
            'ars' => (string) $r->ars,
            'usd' => (string) $r->usd,
            'eur' => (string) $r->eur,
            'btc' => (string) $r->btc,
        ], $rows);
    }

    /**
     * @param  list<array{lb: string, ars: string, usd: string, eur: string, btc: string}>  $rows
     * @return array<string, array{ars: string, usd: string, eur: string, btc: string}>
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

    private function periodSqlExpr(string $column, string $zoom): string
    {
        return match ($zoom) {
            'anual' => "DATE_FORMAT({$column}, '%Y')",
            'fecha' => "DATE({$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function fechaCotizacion(string $periodo, string $zoom): Carbon
    {
        if ($zoom === 'anual') {
            return Carbon::createFromFormat('Y', $periodo)->endOfYear()->startOfDay();
        }
        if ($zoom === 'mensual') {
            return Carbon::createFromFormat('Y-m', $periodo)->endOfMonth()->startOfDay();
        }

        return Carbon::parse($periodo)->startOfDay();
    }
}
