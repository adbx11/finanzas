<?php

namespace App\Jobs;

use App\Services\ConfiguracionService;
use App\Services\CotizacionFetcher;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class FetchCotizacionesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const SCOPE_ALL = 'all';

    public const SCOPE_FIAT = 'fiat';

    public const SCOPE_BTC = 'btc';

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(
        public bool $force = false,
        public string $scope = self::SCOPE_ALL,
    ) {
        if (! in_array($this->scope, [self::SCOPE_ALL, self::SCOPE_FIAT, self::SCOPE_BTC], true)) {
            throw new InvalidArgumentException("Scope de cotizaciones inválido: {$this->scope}");
        }
    }

    public function handle(CotizacionFetcher $fetcher, ConfiguracionService $config): void
    {
        $needsWindow = $this->scope === self::SCOPE_FIAT || $this->scope === self::SCOPE_ALL;

        if (! $this->force && $needsWindow && ! $this->withinScheduleWindow($config)) {
            Log::info('FetchCotizacionesJob omitido fuera de ventana horaria', ['scope' => $this->scope]);

            return;
        }

        try {
            $result = $fetcher->fetch(
                includeFiat: $this->scope !== self::SCOPE_BTC,
                includeBtc: $this->scope !== self::SCOPE_FIAT,
            );
            Log::info('FetchCotizacionesJob OK', ['scope' => $this->scope] + $result);
        } catch (Throwable $e) {
            Log::error('FetchCotizacionesJob falló: '.$e->getMessage(), ['scope' => $this->scope]);
            throw $e;
        }
    }

    private function withinScheduleWindow(ConfiguracionService $config): bool
    {
        $desde = (int) ($config->get('cotizacion.job.hora_desde', '9') ?? '9');
        $hasta = (int) ($config->get('cotizacion.job.hora_hasta', '17') ?? '17');
        $hour = (int) Carbon::now()->format('G');

        return $hour >= $desde && $hour < $hasta;
    }
}
