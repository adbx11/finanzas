<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion')) {
            return;
        }

        $defaults = [
            [
                'clave' => 'cotizacion.job.hora_hasta',
                'valor' => '17',
                'tipo' => 'int',
                'nombre' => 'Job: hora hasta (exclusiva, 0-23)',
                'orden' => 55,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
            [
                'clave' => 'cotizacion.job.ultimo_ok',
                'valor' => '',
                'tipo' => 'string',
                'nombre' => 'Job: último fetch OK',
                'orden' => 60,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
        ];

        foreach ($defaults as $row) {
            if (! DB::table('configuracion')->where('clave', $row['clave'])->exists()) {
                DB::table('configuracion')->insert($row);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuracion')) {
            return;
        }

        DB::table('configuracion')->whereIn('clave', [
            'cotizacion.job.hora_hasta',
            'cotizacion.job.ultimo_ok',
        ])->delete();
    }
};
