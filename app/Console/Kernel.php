<?php

namespace App\Console;

use App\Jobs\FetchCotizacionesJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Cotizaciones: cada hora en ventana laboral (timezone APP_TIMEZONE).
        // La ventana también se valida dentro del job vía cotizacion.job.hora_*.
        $schedule->job(new FetchCotizacionesJob)
            ->hourly()
            ->between('9:00', '17:00')
            ->withoutOverlapping(55)
            ->name('fetch-cotizaciones');

        // Backup solo BD + registro de estado por destino (local / FTP).
        $schedule->command('finanzas:backup')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->name('backup-db');

        $schedule->command('backup:clean')
            ->dailyAt('03:30')
            ->withoutOverlapping()
            ->name('backup-clean');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
