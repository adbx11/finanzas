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
use Throwable;

class FetchCotizacionesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(
        public bool $force = false,
    ) {}

    public function handle(CotizacionFetcher $fetcher, ConfiguracionService $config): void
    {
        if (! $this->force && ! $this->withinScheduleWindow($config)) {
            Log::info('FetchCotizacionesJob omitido fuera de ventana horaria');

            return;
        }

        try {
            $result = $fetcher->fetch();
            Log::info('FetchCotizacionesJob OK', $result);
        } catch (Throwable $e) {
            Log::error('FetchCotizacionesJob falló: '.$e->getMessage());
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
