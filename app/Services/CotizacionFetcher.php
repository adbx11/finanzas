<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\Moneda;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CotizacionFetcher
{
    /** Tope ARS/unidad para fiat; valores mayores suelen ser sentinels del proveedor (mercado cerrado). */
    private const MAX_FIAT_RATE = '1000000';

    /** Tope amplio para BTC/USD y BTC/ARS. */
    private const MAX_BTC_RATE = '100000000000';

    public function __construct(
        private ConfiguracionService $config,
    ) {}

    /**
     * @return array{fiat: list<array{codigo: string, compra: string, venta: string}>, btc: array{btcusd: ?string, btcars: ?string}}
     */
    public function fetch(?Carbon $fecha = null, bool $includeFiat = true, bool $includeBtc = true): array
    {
        if (! $includeFiat && ! $includeBtc) {
            throw new RuntimeException('Debe incluirse fiat y/o BTC.');
        }

        $fecha = ($fecha ?? now())->startOfDay();
        $errors = [];
        $fiat = [];
        $btc = ['btcusd' => null, 'btcars' => null];

        if ($includeFiat) {
            try {
                $fiat = $this->fetchFiat($fecha);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                Log::warning('CotizacionFetcher: fiat no actualizado', ['error' => $e->getMessage()]);
            }
        }

        if ($includeBtc) {
            try {
                $btc = $this->fetchBtc();
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                Log::warning('CotizacionFetcher: BTC no actualizado', ['error' => $e->getMessage()]);
            }
        }

        $btcOk = $btc['btcusd'] !== null || $btc['btcars'] !== null;

        if ($includeFiat && $includeBtc) {
            if ($fiat === [] && ! $btcOk) {
                throw new RuntimeException(implode(' ', $errors) ?: 'No se pudo obtener ninguna cotización.');
            }

            if ($errors !== [] && $fiat === []) {
                // BTC ok pero fiat inválido: avisar sin marcar éxito pleno del job.
                throw new RuntimeException(implode(' ', $errors));
            }
        } elseif ($includeFiat && $fiat === []) {
            throw new RuntimeException(implode(' ', $errors) ?: 'No se pudo obtener cotización fiat.');
        } elseif ($includeBtc && ! $btcOk) {
            throw new RuntimeException(implode(' ', $errors) ?: 'No se pudo obtener cotización BTC.');
        }

        $this->config->set('cotizacion.job.ultimo_ok', now()->toDateTimeString());

        return [
            'fiat' => $fiat,
            'btc' => $btc,
        ];
    }

    /**
     * @return list<array{codigo: string, compra: string, venta: string}>
     */
    private function fetchFiat(Carbon $fecha): array
    {
        $url = $this->config->get('cotizacion.url');
        if (! $url) {
            throw new RuntimeException('Falta configuración cotizacion.url');
        }

        $json = $this->getJson($url);
        $compraRaw = isset($json['compra']) ? str_replace(',', '.', (string) $json['compra']) : null;
        $ventaRaw = isset($json['venta']) ? str_replace(',', '.', (string) $json['venta']) : null;

        if ($compraRaw === null || $ventaRaw === null || ! is_numeric($compraRaw) || ! is_numeric($ventaRaw)) {
            throw new RuntimeException('Respuesta fiat inválida desde '.$url);
        }

        $compra = Money::round((string) $compraRaw, 10);
        $venta = Money::round((string) $ventaRaw, 10);
        $this->assertPlausibleRate($compra, $venta, self::MAX_FIAT_RATE, 'fiat USD/ARS');

        $usdeur = '1';

        $saved = [];
        foreach (['USD', 'BTC'] as $codigo) {
            $this->saveRate($codigo, $fecha, $compra, $venta);
            $saved[] = ['codigo' => $codigo, 'compra' => $compra, 'venta' => $venta];
        }

        $eurCompra = Money::div($compra, $usdeur, 4);
        $eurVenta = Money::div($venta, $usdeur, 4);
        $this->saveRate('EUR', $fecha, $eurCompra, $eurVenta);
        $saved[] = ['codigo' => 'EUR', 'compra' => $eurCompra, 'venta' => $eurVenta];

        return $saved;
    }

    /**
     * @return array{btcusd: ?string, btcars: ?string}
     */
    private function fetchBtc(): array
    {
        $url = $this->config->get(
            'cotizacion.coingecko.url',
            'https://api.coingecko.com/api/v3/exchange_rates'
        );

        $json = $this->getJson($url);
        $rates = $json['rates'] ?? null;
        if (! is_array($rates)) {
            throw new RuntimeException('Respuesta CoinGecko inválida');
        }

        $btcusd = isset($rates['usd']['value']) ? Money::round((string) $rates['usd']['value'], 10) : null;
        $btcars = isset($rates['ars']['value']) ? Money::round((string) $rates['ars']['value'], 10) : null;

        if ($btcusd !== null && ! $this->isPlausibleRate($btcusd, self::MAX_BTC_RATE)) {
            Log::warning('CotizacionFetcher: BTC/USD descartado por estar fuera de rango', ['value' => $btcusd]);
            $btcusd = null;
        }
        if ($btcars !== null && ! $this->isPlausibleRate($btcars, self::MAX_BTC_RATE)) {
            Log::warning('CotizacionFetcher: BTC/ARS descartado por estar fuera de rango', ['value' => $btcars]);
            $btcars = null;
        }

        if ($btcusd !== null) {
            $this->config->set('cotizacion.btcusd', $btcusd);
        }
        if ($btcars !== null) {
            $this->config->set('cotizacion.btcars', $btcars);
        }

        return [
            'btcusd' => $btcusd,
            'btcars' => $btcars,
        ];
    }

    private function assertPlausibleRate(string $compra, string $venta, string $max, string $label): void
    {
        foreach (['compra' => $compra, 'venta' => $venta] as $side => $rate) {
            if (! $this->isPlausibleRate($rate, $max)) {
                throw new RuntimeException(
                    "Cotización {$label} {$side} inválida ({$rate}). "
                    .'El proveedor puede estar cerrado o devolvió un valor sentinel; no se guardó.'
                );
            }
        }
    }

    private function isPlausibleRate(string $rate, string $max): bool
    {
        return bccomp($rate, '0', 10) > 0 && bccomp($rate, $max, 10) <= 0;
    }

    private function saveRate(string $codigo, Carbon $fecha, string $compra, string $venta): void
    {
        $moneda = Moneda::query()->where('codigo', $codigo)->first();
        if (! $moneda) {
            Log::warning("CotizacionFetcher: moneda {$codigo} no encontrada");

            return;
        }

        Cotizacion::query()->updateOrCreate(
            [
                'id_moneda' => $moneda->id,
                'fecha' => $fecha->toDateString(),
            ],
            [
                'compra' => $compra,
                'venta' => $venta,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $verify = filter_var(
            $this->config->get('cotizacion.http.verify_ssl', app()->environment('production') ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN
        );

        $response = Http::timeout(20)
            ->withOptions(['verify' => $verify])
            ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'ADB-Finanzas/2'])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' al consultar '.$url);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('JSON inválido desde '.$url);
        }

        return $data;
    }
}
