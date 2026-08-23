<?php

namespace App\Console\Commands;

use App\Services\CotizacionFetcher;
use Illuminate\Console\Command;
use Throwable;

class FetchCotizacionesCommand extends Command
{
    protected $signature = 'finanzas:fetch-cotizaciones';

    protected $description = 'Obtiene cotizaciones fiat (Ámbito) y BTC (CoinGecko)';

    public function handle(CotizacionFetcher $fetcher): int
    {
        try {
            $result = $fetcher->fetch();
            $this->info('Fiat:');
            foreach ($result['fiat'] as $row) {
                $this->line("  {$row['codigo']}: compra {$row['compra']} / venta {$row['venta']}");
            }
            $this->info('BTC config: USD='.($result['btc']['btcusd'] ?? '—').' ARS='.($result['btc']['btcars'] ?? '—'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
