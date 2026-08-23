<?php

namespace App\Console\Commands;

use App\Services\BackupStatusService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'finanzas:backup {--disk= : Solo subir a este disco}';

    protected $description = 'Ejecuta backup de BD (--only-db) y registra estado por destino';

    public function handle(BackupStatusService $backups): int
    {
        $disk = $this->option('disk') ?: null;
        $this->info('Iniciando backup'.($disk ? " → {$disk}" : '').'...');

        $result = $backups->run($disk);

        foreach ($result['disks'] as $row) {
            $mark = ($row['uploaded_ok'] ?? false) ? 'OK' : 'FAIL';
            $this->line("  [{$mark}] {$row['label']} ({$row['disk']})".(
                empty($row['error']) ? '' : ' — '.$row['error']
            ));
        }

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
