<?php

namespace App\Services\Crypto;

use App\Models\CryptoCoin;
use App\Models\CryptoWallet;
use App\Services\ConfiguracionService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CryptoService
{
    /** @var array<string, string|null> */
    private array $priceUsdCache = [];

    /** @var array<string, string|null> */
    private array $priceArsCache = [];

    public function __construct(
        private ConfiguracionService $config,
    ) {}

    /**
     * Precio de mercado USD para valorizar holdings.
     * BTC: cotizacion.btcusd (CoinGecko). Otras coins: último price_usd registrado.
     */
    public function currentPriceUsd(string $coin): ?string
    {
        $coin = strtoupper(trim($coin));
        if ($coin === '') {
            return null;
        }

        if (array_key_exists($coin, $this->priceUsdCache)) {
            return $this->priceUsdCache[$coin];
        }

        if ($coin === 'BTC') {
            $price = $this->config->get('cotizacion.btcusd');
            if ($price !== null && $price !== '' && is_numeric($price) && bccomp($price, '0', 10) > 0) {
                return $this->priceUsdCache[$coin] = Money::round($price, 2);
            }
        }

        $last = DB::table('coin_transactions')
            ->whereRaw('UPPER(coin) = ?', [$coin])
            ->whereNotNull('price_usd')
            ->where('price_usd', '>', 0)
            ->orderByDesc('ts')
            ->orderByDesc('id')
            ->value('price_usd');

        if ($last === null || ! is_numeric((string) $last)) {
            return $this->priceUsdCache[$coin] = null;
        }

        return $this->priceUsdCache[$coin] = Money::round((string) $last, 2);
    }

    public function currentPriceArs(string $coin): ?string
    {
        $coin = strtoupper(trim($coin));
        if (array_key_exists($coin, $this->priceArsCache)) {
            return $this->priceArsCache[$coin];
        }

        if ($coin === 'BTC') {
            $price = $this->config->get('cotizacion.btcars');
            if ($price !== null && $price !== '' && is_numeric($price) && bccomp($price, '0', 10) > 0) {
                return $this->priceArsCache[$coin] = Money::round($price, 2);
            }
        }

        $usd = $this->currentPriceUsd($coin);
        if ($usd === null) {
            return $this->priceArsCache[$coin] = null;
        }

        // USD blue (venta) desde cotizaciones vía config btcars/btcusd, o no disponible.
        $btcusd = $this->config->get('cotizacion.btcusd');
        $btcars = $this->config->get('cotizacion.btcars');
        if ($btcusd && $btcars && is_numeric($btcusd) && is_numeric($btcars) && bccomp($btcusd, '0', 10) > 0) {
            $usdArs = Money::div($btcars, $btcusd, 8);

            return $this->priceArsCache[$coin] = Money::round(Money::mul($usd, $usdArs, 8), 2);
        }

        return $this->priceArsCache[$coin] = null;
    }

    /**
     * @param  array{quantity: string, investment_usd: string, price_usd_compra: string}  $row
     * @return array
     */
    private function withMarketValue(array $row, string $coin): array
    {
        $priceUsd = $this->currentPriceUsd($coin);
        $priceArs = $this->currentPriceArs($coin);
        $qty = (string) ($row['quantity'] ?? '0');

        $valueUsd = $priceUsd !== null
            ? Money::round(Money::mul($qty, $priceUsd, 12), 2)
            : null;
        $valueArs = $priceArs !== null
            ? Money::round(Money::mul($qty, $priceArs, 12), 2)
            : null;

        $investment = (string) ($row['investment_usd'] ?? '0');
        $pnlUsd = $valueUsd !== null
            ? Money::round(Money::sub($valueUsd, $investment, 2), 2)
            : null;

        return array_merge($row, [
            'price_usd_actual' => $priceUsd,
            'price_ars_actual' => $priceArs,
            'value_usd' => $valueUsd,
            'value_ars' => $valueArs,
            'pnl_usd' => $pnlUsd,
        ]);
    }

    /**
     * Resumen por moneda (dashboard).
     *
     * @return list<array>
     */
    public function balances(?string $coin = null): array
    {
        $bindings = [];
        $where = '';
        if ($coin !== null && $coin !== '') {
            $where = 'WHERE UPPER(c.coin) = ?';
            $bindings[] = strtoupper(trim($coin));
        }

        $rows = DB::select(
            "SELECT c.coin AS coin,
                    SUM(CAST(c.quantity AS DECIMAL(40,20))) AS quantity,
                    IF(
                        SUM(IF(CAST(c.quantity AS DECIMAL(40,20)) > 0, CAST(c.quantity AS DECIMAL(40,20)), 0)) > 0,
                        SUM(IF(CAST(c.quantity AS DECIMAL(40,20)) > 0, c.price_usd * CAST(c.quantity AS DECIMAL(40,20)), 0))
                            / SUM(IF(CAST(c.quantity AS DECIMAL(40,20)) > 0, CAST(c.quantity AS DECIMAL(40,20)), 0)),
                        0
                    ) AS price_usd_compra,
                    SUM(c.price_usd * CAST(c.quantity AS DECIMAL(40,20))) AS investment_usd,
                    COUNT(DISTINCT c.wallet) AS wallets
             FROM coin_transactions AS c
             {$where}
             GROUP BY c.coin
             ORDER BY quantity DESC",
            $bindings
        );

        return collect($rows)->map(function ($r) {
            $coin = strtoupper((string) $r->coin);
            $base = [
                'coin' => $coin,
                'quantity' => (string) ($r->quantity ?? '0'),
                'price_usd_compra' => Money::round((string) ($r->price_usd_compra ?? '0'), 2),
                'investment_usd' => Money::round((string) ($r->investment_usd ?? '0'), 2),
                'wallets' => (int) $r->wallets,
            ];

            return $this->withMarketValue($base, $coin);
        })->all();
    }

    /**
     * Detalle por wallet (dashboard).
     *
     * @return list<array>
     */
    public function balancesByWallet(?string $coin = null): array
    {
        $bindings = [];
        $where = '';
        if ($coin !== null && $coin !== '') {
            $where = 'WHERE UPPER(c.coin) = ?';
            $bindings[] = strtoupper(trim($coin));
        }

        $rows = DB::select(
            "SELECT c.coin AS coin,
                    COALESCE(c.wallet, '') AS wallet,
                    SUM(CAST(c.quantity AS DECIMAL(40,20))) AS quantity,
                    IF(
                        SUM(IF(CAST(c.quantity AS DECIMAL(40,20)) > 0, CAST(c.quantity AS DECIMAL(40,20)), 0)) > 0,
                        SUM(IF(CAST(c.quantity AS DECIMAL(40,20)) > 0, c.price_usd * CAST(c.quantity AS DECIMAL(40,20)), 0))
                            / SUM(IF(CAST(c.quantity AS DECIMAL(40,20)) > 0, CAST(c.quantity AS DECIMAL(40,20)), 0)),
                        0
                    ) AS price_usd_compra,
                    SUM(c.price_usd * CAST(c.quantity AS DECIMAL(40,20))) AS investment_usd
             FROM coin_transactions AS c
             {$where}
             GROUP BY c.coin, c.wallet
             ORDER BY c.coin, quantity DESC",
            $bindings
        );

        return collect($rows)->map(function ($r) {
            $coin = strtoupper((string) $r->coin);
            $base = [
                'coin' => $coin,
                'wallet' => (string) ($r->wallet ?? ''),
                'quantity' => (string) ($r->quantity ?? '0'),
                'price_usd_compra' => Money::round((string) ($r->price_usd_compra ?? '0'), 2),
                'investment_usd' => Money::round((string) ($r->investment_usd ?? '0'), 2),
            ];

            return $this->withMarketValue($base, $coin);
        })->all();
    }

    /**
     * Informe anual acumulado (inversión / cantidad / valuación).
     *
     * @return array{
     *   coin: string,
     *   price_usd: string,
     *   filas: list<array>,
     *   totales: array
     * }
     */
    public function yearlyReport(string $coin = 'BTC', string $priceUsd = '88000', ?int $yearFrom = null, ?int $yearTo = null): array
    {
        $coin = strtoupper(trim($coin)) ?: 'BTC';
        $priceUsd = Money::round($priceUsd !== '' ? $priceUsd : '88000', 2);

        $bindings = [$coin];
        $yearSql = '';
        if ($yearFrom !== null) {
            $yearSql .= ' AND YEAR(ts) >= ?';
            $bindings[] = $yearFrom;
        }
        if ($yearTo !== null) {
            $yearSql .= ' AND YEAR(ts) <= ?';
            $bindings[] = $yearTo;
        }

        $rows = DB::select(
            "SELECT
                year,
                yearly_investment_usd,
                yearly_quantity,
                SUM(yearly_investment_usd) OVER (ORDER BY year) AS cumulative_investment_usd,
                SUM(yearly_quantity) OVER (ORDER BY year) AS cumulative_quantity
             FROM (
                SELECT
                    YEAR(ts) AS year,
                    SUM(price_usd * CAST(quantity AS DECIMAL(40,20))) AS yearly_investment_usd,
                    SUM(CAST(quantity AS DECIMAL(40,20))) AS yearly_quantity
                FROM coin_transactions
                WHERE UPPER(coin) = ?
                  {$yearSql}
                GROUP BY YEAR(ts)
             ) t
             ORDER BY year",
            $bindings
        );

        $filas = [];
        foreach ($rows as $row) {
            $cumQty = (string) ($row->cumulative_quantity ?? '0');
            $cumInv = Money::round((string) ($row->cumulative_investment_usd ?? '0'), 2);
            $cumValue = Money::round(Money::mul($priceUsd, $cumQty, 12), 2);
            $cumPnL = Money::sub($cumValue, $cumInv, 2);

            $filas[] = [
                'year' => (int) $row->year,
                'yearly_investment_usd' => Money::round((string) ($row->yearly_investment_usd ?? '0'), 2),
                'yearly_quantity' => (string) ($row->yearly_quantity ?? '0'),
                'cumulative_investment_usd' => $cumInv,
                'cumulative_quantity' => $cumQty,
                'cumulative_market_value' => $cumValue,
                'cumulative_pnl' => $cumPnL,
            ];
        }

        $last = $filas === [] ? null : $filas[array_key_last($filas)];

        return [
            'coin' => $coin,
            'price_usd' => $priceUsd,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'filas' => $filas,
            'totales' => [
                'cumulative_investment_usd' => $last['cumulative_investment_usd'] ?? '0',
                'cumulative_quantity' => $last['cumulative_quantity'] ?? '0',
                'cumulative_market_value' => $last['cumulative_market_value'] ?? '0',
                'cumulative_pnl' => $last['cumulative_pnl'] ?? '0',
            ],
        ];
    }

    /**
     * @return list<array{id: int, codigo: string, descripcion: ?string}>
     */
    public function catalogCoins(bool $onlyEnabled = true): array
    {
        $q = CryptoCoin::query()->orderBy('codigo');
        if ($onlyEnabled) {
            $q->habilitadas();
        }

        return $q->get(['id', 'codigo', 'descripcion'])->map(fn (CryptoCoin $c) => [
            'id' => $c->id,
            'codigo' => $c->codigo,
            'descripcion' => $c->descripcion,
        ])->all();
    }

    /**
     * @return list<array{id: int, codigo: string, descripcion: ?string, id_coin: ?int, coin: ?string}>
     */
    public function catalogWallets(?string $coinCodigo = null, bool $onlyEnabled = true): array
    {
        $q = CryptoWallet::query()->with('coin')->orderBy('codigo');
        if ($onlyEnabled) {
            $q->habilitadas();
        }
        if ($coinCodigo) {
            $q->where(function ($inner) use ($coinCodigo) {
                $inner->whereHas('coin', fn ($c) => $c->whereRaw('UPPER(codigo) = ?', [strtoupper($coinCodigo)]))
                    ->orWhereNull('id_coin');
            });
        }

        return $q->get()->map(fn (CryptoWallet $w) => [
            'id' => $w->id,
            'codigo' => $w->codigo,
            'descripcion' => $w->descripcion,
            'id_coin' => $w->id_coin,
            'coin' => $w->coin?->codigo,
        ])->all();
    }

    /**
     * @return list<string>
     */
    public function distinctCoins(): array
    {
        $fromCatalog = CryptoCoin::query()->habilitadas()->orderBy('codigo')->pluck('codigo')
            ->map(fn ($c) => strtoupper((string) $c))
            ->all();

        if ($fromCatalog !== []) {
            return array_values(array_unique($fromCatalog));
        }

        return DB::table('coin_transactions')
            ->whereNotNull('coin')
            ->distinct()
            ->orderBy('coin')
            ->pluck('coin')
            ->map(fn ($c) => strtoupper((string) $c))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function distinctWallets(?string $coin = null): array
    {
        return collect($this->catalogWallets($coin))
            ->pluck('codigo')
            ->values()
            ->all();
    }
}
