<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;
use Throwable;

class BackupStatusService
{
    public function __construct(
        private ConfiguracionService $config,
    ) {}

    /**
     * @return list<string>
     */
    public function destinationDisks(): array
    {
        $disks = config('backup.backup.destination.disks', ['local']);

        return array_values(array_filter(array_map('strval', $disks)));
    }

    /**
     * @return array{
     *   name: string,
     *   disks: list<array>,
     *   last_run: ?array,
     *   schedule: array{backup_at: string, clean_at: string}
     * }
     */
    public function status(): array
    {
        $name = (string) config('backup.backup.name');
        $disks = [];

        foreach ($this->destinationDisks() as $diskName) {
            $disks[] = $this->diskStatus($diskName, $name);
        }

        $lastRun = $this->readLastRunFile();

        return [
            'name' => $name,
            'disks' => $disks,
            'last_run' => $lastRun,
            'last_ok' => $this->config->get('backup.ultimo_ok')
                ?: ((! empty($lastRun['ok'])) ? ($lastRun['at'] ?? null) : null),
            'last_error' => $this->config->get('backup.ultimo_error'),
            'schedule' => [
                'backup_at' => '03:00',
                'clean_at' => '03:30',
            ],
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   exit_code: int,
     *   disks: list<array>
     * }
     */
    public function run(?string $onlyDisk = null): array
    {
        $startedAt = now()->toDateTimeString();
        set_time_limit(300);

        $params = ['--only-db' => true];
        if ($onlyDisk) {
            $params['--only-to-disk'] = $onlyDisk;
        }

        try {
            $exitCode = Artisan::call('backup:run', $params);
            $output = Artisan::output();
            $ok = $exitCode === 0;

            $diskSnapshots = $this->snapshotDisksAfterRun($startedAt);
            $allDisksOk = collect($diskSnapshots)->every(fn (array $d) => $d['uploaded_ok'] ?? false);

            $result = [
                'at' => now()->toDateTimeString(),
                'started_at' => $startedAt,
                'ok' => $ok && $allDisksOk,
                'exit_code' => $exitCode,
                'message' => $ok
                    ? ($allDisksOk ? 'Backup completado.' : 'Backup parcial: revisá destinos FTP.')
                    : (trim($output) !== '' ? trim($output) : 'El backup falló.'),
                'disks' => $diskSnapshots,
                'triggered_by' => 'manual',
            ];

            $this->persistResult($result);

            return $result;
        } catch (Throwable $e) {
            Log::error('Backup manual falló: '.$e->getMessage());

            $diskSnapshots = $this->snapshotDisksAfterRun($startedAt);
            $result = [
                'at' => now()->toDateTimeString(),
                'started_at' => $startedAt,
                'ok' => false,
                'exit_code' => 1,
                'message' => $e->getMessage(),
                'disks' => $diskSnapshots,
                'triggered_by' => 'manual',
            ];
            $this->persistResult($result);

            return $result;
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function persistResult(array $result): void
    {
        $path = $this->lastRunPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (! empty($result['ok'])) {
            $this->config->set('backup.ultimo_ok', (string) ($result['at'] ?? now()->toDateTimeString()));
            $this->config->set('backup.ultimo_error', '');
        } else {
            $message = (string) ($result['message'] ?? 'Error');
            if (strlen($message) > 240) {
                $message = substr($message, 0, 237).'...';
            }
            $this->config->set('backup.ultimo_error', $message);
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    private function readLastRunFile(): ?array
    {
        $path = $this->lastRunPath();
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function lastRunPath(): string
    {
        return storage_path('app/backup-status/last-run.json');
    }

    /**
     * @return array{
     *   disk: string,
     *   label: string,
     *   driver: string,
     *   reachable: bool,
     *   healthy: bool,
     *   health_failures: list<string>,
     *   connection_error: ?string,
     *   backups_count: int,
     *   newest: ?array{date: string, size: int, path: string, human_size: string},
     *   host: ?string
     * }
     */
    private function diskStatus(string $diskName, string $backupName): array
    {
        $driver = (string) config("filesystems.disks.{$diskName}.driver", 'unknown');
        $host = config("filesystems.disks.{$diskName}.host");
        $label = match ($diskName) {
            'local' => 'Local',
            'backup_ftp' => 'FTP 1',
            'backup_ftp_2' => 'FTP 2',
            default => $diskName,
        };

        $destination = BackupDestination::create($diskName, $backupName);
        $reachable = $destination->isReachable();
            $connectionError = $destination->connectionError?->getMessage();

            $newest = null;
            $count = 0;
            $healthy = false;
            $healthFailures = [];

            if ($reachable) {
                try {
                    $newestBackup = $destination->newestBackup();
                    $count = $destination->backups()->count();
                    if ($newestBackup) {
                        $size = (int) $newestBackup->sizeInBytes();
                        $newest = [
                            'date' => $newestBackup->date()->timezone(config('app.timezone'))->toDateTimeString(),
                            'size' => $size,
                            'path' => $newestBackup->path(),
                            'human_size' => $this->humanSize($size),
                        ];
                    }
                } catch (Throwable $e) {
                    $reachable = false;
                    $connectionError = $e->getMessage();
                }
            }

        try {
            $statuses = BackupDestinationStatusFactory::createForMonitorConfig(config('backup.monitor_backups'));
            foreach ($statuses as $status) {
                if ($status->backupDestination()->diskName() !== $diskName) {
                    continue;
                }
                $healthy = $status->isHealthy();
                if (! $healthy) {
                    $healthFailures[] = $status->getHealthCheckFailure()?->exception()?->getMessage()
                        ?? 'Destino no saludable';
                }
            }
        } catch (Throwable $e) {
            $healthFailures[] = $e->getMessage();
        }

        return [
            'disk' => $diskName,
            'label' => $label,
            'driver' => $driver,
            'reachable' => $reachable,
            'healthy' => $healthy,
            'health_failures' => array_values(array_filter($healthFailures)),
            'connection_error' => $connectionError,
            'backups_count' => $count,
            'newest' => $newest,
            'host' => $host ? (string) $host : null,
        ];
    }

    /**
     * @return list<array{disk: string, label: string, uploaded_ok: bool, newest: ?array, error: ?string}>
     */
    private function snapshotDisksAfterRun(string $startedAt): array
    {
        $name = (string) config('backup.backup.name');
        $started = \Carbon\Carbon::parse($startedAt)->subMinute();
        $out = [];

        foreach ($this->destinationDisks() as $diskName) {
            $status = $this->diskStatus($diskName, $name);
            $newest = $status['newest'];
            $uploadedOk = false;
            $error = $status['connection_error'];

            if ($newest && $status['reachable']) {
                $newestAt = \Carbon\Carbon::parse($newest['date']);
                $uploadedOk = $newestAt->greaterThanOrEqualTo($started);
                if (! $uploadedOk) {
                    $error = 'No hay un backup nuevo en este destino tras la corrida.';
                }
            } elseif (! $status['reachable']) {
                $error = $error ?: 'Destino no alcanzable';
            }

            $out[] = [
                'disk' => $diskName,
                'label' => $status['label'],
                'uploaded_ok' => $uploadedOk,
                'newest' => $newest,
                'error' => $uploadedOk ? null : $error,
            ];
        }

        return $out;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
