<?php

namespace App\Console\Commands;

use App\Services\CotizacionFetcher;
use Illuminate\Console\Command;
use Throwable;

class FetchCotizacionesCommand extends Command
{
    protected $signature = 'finanzas:fetch-cotizaciones {--only= : Solo fiat o btc}';

    protected $description = 'Obtiene cotizaciones fiat (Ámbito) y/o BTC (CoinGecko)';

    public function handle(CotizacionFetcher $fetcher): int
    {
        $only = strtolower((string) $this->option('only'));
        $includeFiat = $only === '' || $only === 'fiat';
        $includeBtc = $only === '' || $only === 'btc';

        if (! in_array($only, ['', 'fiat', 'btc'], true)) {
            $this->error('Opción --only inválida. Usá fiat, btc o omitila.');

            return self::FAILURE;
        }

        try {
            $result = $fetcher->fetch(includeFiat: $includeFiat, includeBtc: $includeBtc);
            if ($includeFiat) {
                $this->info('Fiat:');
                foreach ($result['fiat'] as $row) {
                    $this->line("  {$row['codigo']}: compra {$row['compra']} / venta {$row['venta']}");
                }
            }
            if ($includeBtc) {
                $this->info('BTC config: USD='.($result['btc']['btcusd'] ?? '—').' ARS='.($result['btc']['btcars'] ?? '—'));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
