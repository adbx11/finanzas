<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbVerifyCommand extends Command
{
    protected $signature = 'finanzas:db-verify';

    protected $description = 'Verifica conexión y conteos de tablas legacy';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
            $this->info('Conexión OK: '.config('database.connections.mysql.database'));
        } catch (\Throwable $e) {
            $this->error('Error de conexión: '.$e->getMessage());

            return self::FAILURE;
        }

        $tables = [
            'monedas',
            'cuentas',
            'asientos',
            'asiento_items',
            'ingresos',
            'pagos',
            'cotizaciones',
            'sec_usuario',
        ];

        $rows = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$table, '—', 'no existe'];
                continue;
            }

            $count = DB::table($table)->count();
            $rows[] = [$table, number_format($count, 0, ',', '.'), 'ok'];
        }

        $this->table(['Tabla', 'Registros', 'Estado'], $rows);

        if (Schema::hasTable('asientos')) {
            $ultima = DB::table('asientos')->max('fecha');
            $this->line('Última fecha de asiento: '.($ultima ?: '—'));
        }

        return self::SUCCESS;
    }
}
