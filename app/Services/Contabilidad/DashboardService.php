<?php

namespace App\Services\Contabilidad;

use App\Models\Moneda;
use App\Services\CotizacionService;
use App\Services\Crypto\CryptoService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @var array<string, string> */
    private array $rateCache = [];

    /** @var array<string, Moneda>|null */
    private ?array $monedasByCodigo = null;

    public function __construct(
        private CotizacionService $cotizacionService,
        private CryptoService $cryptoService,
    ) {}

    /**
     * @return array{
     *   hasta: string,
     *   saldos: list<array>,
     *   distribucion_moneda: list<array{label: string, value: string, percent: float}>,
     *   distribucion_cuenta_ars: list<array{label: string, value: string}>,
     *   distribucion_cuenta_usd: list<array{label: string, value: string}>,
     *   crypto: list<array>,
     *   crypto_coin: string,
     *   crypto_coins: list<string>,
     *   crypto_wallets: list<array>
     * }
     */
    public function resumen(Carbon $hasta, string $cryptoCoin = 'BTC'): array
    {
        return array_merge(
            $this->saldosSection($hasta),
            $this->distribucionSection($hasta),
            $this->cryptoSection($cryptoCoin),
        );
    }

    /**
     * @return array{hasta: string, saldos: list<array>}
     */
    public function saldosSection(Carbon $hasta): array
    {
        $cacheKey = 'dashboard.saldos.'.$hasta->toDateString();
        $ttl = $hasta->isToday() ? 45 : 120;

        return Cache::remember($cacheKey, $ttl, function () use ($hasta) {
            $this->warmRates($hasta);
            $saldosBase = $this->activoPorMoneda($hasta);
            $totales = $this->totalesConvertidos($saldosBase, $hasta);

            $saldos = [];
            foreach ($saldosBase as $row) {
                $codigo = $row['codigo'];
                $saldos[] = array_merge($row, [
                    'total' => $totales[$codigo] ?? $row['value'],
                    'cotizacion' => $this->rateForCodigo($codigo, $hasta),
                ]);
            }

            return [
                'hasta' => $hasta->toDateString(),
                'saldos' => $saldos,
            ];
        });
    }

    /**
     * @return array{
     *   hasta: string,
     *   distribucion_moneda: list<array{label: string, value: string, percent: float}>,
     *   distribucion_cuenta_ars: list<array{label: string, value: string}>,
     *   distribucion_cuenta_usd: list<array{label: string, value: string}>
     * }
     */
    public function distribucionSection(Carbon $hasta): array
    {
        $cacheKey = 'dashboard.dist.'.$hasta->toDateString();
        $ttl = $hasta->isToday() ? 45 : 120;

        return Cache::remember($cacheKey, $ttl, function () use ($hasta) {
            $this->warmRates($hasta);
            // Reusa el mismo agregado que saldos (cache hit si ya corrió saldos).
            $saldosBase = $this->activoPorMonedaCached($hasta);

            $porCuenta = $this->distribucionPorCuentaAmbas($hasta);

            return [
                'hasta' => $hasta->toDateString(),
                'distribucion_moneda' => $this->distribucionPorMonedaFromSaldos($saldosBase, $hasta),
                'distribucion_cuenta_ars' => $porCuenta['ARS'],
                'distribucion_cuenta_usd' => $porCuenta['USD'],
            ];
        });
    }

    /**
     * Resumen crypto sin detalle de wallets (lazy).
     *
     * @return array{
     *   crypto: list<array>,
     *   crypto_coin: string,
     *   crypto_coins: list<string>,
     *   crypto_wallets: list<array>
     * }
     */
    public function cryptoSection(string $cryptoCoin = 'BTC'): array
    {
        $cryptoCoin = strtoupper(trim($cryptoCoin)) ?: 'BTC';

        return [
            'crypto' => $this->cryptoService->balances($cryptoCoin),
            'crypto_coin' => $cryptoCoin,
            'crypto_coins' => $this->cryptoService->distinctCoins(),
            'crypto_wallets' => [],
        ];
    }

    /**
     * @return list<array>
     */
    public function cryptoWallets(string $cryptoCoin = 'BTC'): array
    {
        return $this->cryptoService->balancesByWallet($cryptoCoin);
    }

    /**
     * @return list<array{id: int, codigo: string, simbolo: string, value: string}>
     */
    private function activoPorMoneda(Carbon $hasta): array
    {
        $rows = DB::select(
            "SELECT mon.id, mon.codigo, mon.simbolo,
                    ROUND(SUM(ai.debe_origen - ai.haber_origen), 2) AS value
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON ai.id_asiento = a.id
             INNER JOIN cuentas AS c ON c.id = ai.id_cuenta AND c.tipo_cuenta = 'A'
             INNER JOIN monedas AS mon ON mon.id = ai.id_moneda
             WHERE a.fecha <= ?
             GROUP BY mon.id, mon.codigo, mon.simbolo
             ORDER BY mon.id",
            [$hasta->toDateString()]
        );

        return collect($rows)->map(fn ($r) => [
            'id' => (int) $r->id,
            'codigo' => (string) $r->codigo,
            'simbolo' => (string) $r->simbolo,
            'value' => Money::round((string) ($r->value ?? '0'), 2),
        ])->all();
    }

    /**
     * @return list<array{id: int, codigo: string, simbolo: string, value: string}>
     */
    private function activoPorMonedaCached(Carbon $hasta): array
    {
        $saldos = $this->saldosSection($hasta)['saldos'] ?? [];

        return collect($saldos)->map(fn (array $row) => [
            'id' => (int) ($row['id'] ?? 0),
            'codigo' => (string) $row['codigo'],
            'simbolo' => (string) $row['simbolo'],
            'value' => (string) $row['value'],
        ])->all();
    }

    /**
     * @param  list<array{codigo: string, value: string}>  $saldos
     * @return array<string, string>
     */
    private function totalesConvertidos(array $saldos, Carbon $hasta): array
    {
        $byCodigo = [];
        foreach ($saldos as $row) {
            $byCodigo[$row['codigo']] = $row['value'];
        }

        $totalArs = '0';
        foreach ($byCodigo as $codigo => $value) {
            $rate = $this->rateForCodigo($codigo, $hasta);
            $totalArs = Money::add($totalArs, Money::round(Money::mul($value, $rate, 8), 2), 2);
        }

        $totales = [];
        foreach ($byCodigo as $codigo => $_) {
            $rate = $this->rateForCodigo($codigo, $hasta);
            $totales[$codigo] = Money::isZero($rate)
                ? '0.00'
                : Money::round(Money::div($totalArs, $rate, 8), 2);
        }

        return $totales;
    }

    /**
     * @param  list<array{codigo: string, simbolo: string, value: string}>  $saldos
     * @return list<array{label: string, value: string, percent: float}>
     */
    private function distribucionPorMonedaFromSaldos(array $saldos, Carbon $hasta): array
    {
        $rows = array_values(array_filter(
            $saldos,
            fn (array $row) => ! Money::isZero($row['value']),
        ));

        usort($rows, fn ($a, $b) => bccomp($b['value'], $a['value'], 8));

        $totalArs = '0';
        $items = [];
        foreach ($rows as $r) {
            $value = $r['value'];
            $rate = $this->rateForCodigo($r['codigo'], $hasta);
            $valueArs = Money::round(Money::mul($value, $rate, 8), 2);
            $totalArs = Money::add($totalArs, $valueArs, 2);
            $items[] = [
                'label' => $r['simbolo'],
                'value' => $value,
                'value_ars' => $valueArs,
            ];
        }

        return collect($items)->map(function ($item) use ($totalArs) {
            $percent = Money::isZero($totalArs)
                ? 0.0
                : (float) Money::round(Money::mul(Money::div($item['value_ars'], $totalArs, 8), '100', 8), 2);

            return [
                'label' => $item['label'],
                'value' => $item['value'],
                'percent' => $percent,
            ];
        })->all();
    }

    /**
     * Una query para ARS + USD (top 12 c/u).
     *
     * @return array{ARS: list<array{label: string, value: string}>, USD: list<array{label: string, value: string}>}
     */
    private function distribucionPorCuentaAmbas(Carbon $hasta): array
    {
        $rows = DB::select(
            "SELECT mon.codigo AS moneda,
                    c.descripcion AS label,
                    ROUND(SUM(ai.debe_origen - ai.haber_origen), 2) AS value
             FROM asientos AS a
             INNER JOIN asiento_items AS ai ON ai.id_asiento = a.id
             INNER JOIN cuentas AS c ON c.id = ai.id_cuenta AND c.tipo_cuenta = 'A'
             INNER JOIN monedas AS mon ON mon.id = ai.id_moneda AND mon.codigo IN ('ARS', 'USD')
             WHERE a.fecha <= ?
             GROUP BY mon.codigo, c.id, c.descripcion
             HAVING value <> 0
             ORDER BY mon.codigo, value DESC",
            [$hasta->toDateString()]
        );

        $out = ['ARS' => [], 'USD' => []];
        foreach ($rows as $r) {
            $codigo = (string) $r->moneda;
            if (! isset($out[$codigo]) || count($out[$codigo]) >= 12) {
                continue;
            }
            $out[$codigo][] = [
                'label' => (string) $r->label,
                'value' => Money::round((string) ($r->value ?? '0'), 2),
            ];
        }

        return $out;
    }

    private function warmRates(Carbon $hasta): void
    {
        $this->cotizacionService->getRatesForDate($hasta);
        $this->monedasByCodigo();
    }

    /**
     * @return array<string, Moneda>
     */
    private function monedasByCodigo(): array
    {
        if ($this->monedasByCodigo !== null) {
            return $this->monedasByCodigo;
        }

        $this->monedasByCodigo = [];
        foreach (Moneda::query()->get() as $moneda) {
            $this->monedasByCodigo[(string) $moneda->codigo] = $moneda;
        }

        return $this->monedasByCodigo;
    }

    private function rateForCodigo(string $codigo, Carbon $hasta): string
    {
        $cacheKey = $codigo.'|'.$hasta->toDateString();
        if (isset($this->rateCache[$cacheKey])) {
            return $this->rateCache[$cacheKey];
        }

        $moneda = $this->monedasByCodigo()[$codigo] ?? null;
        if (! $moneda) {
            return $this->rateCache[$cacheKey] = '1';
        }

        if ($moneda->local) {
            return $this->rateCache[$cacheKey] = '1';
        }

        $rates = $this->cotizacionService->getRatesForDate($hasta);

        return $this->rateCache[$cacheKey] = $rates[(int) $moneda->id] ?? '1';
    }
}
