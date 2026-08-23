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
                'clave' => 'backup.ultimo_ok',
                'valor' => '',
                'tipo' => 'string',
                'nombre' => 'Backup: último OK',
                'orden' => 10,
                'grupo_orden' => 20,
                'grupo' => 'Backup',
            ],
            [
                'clave' => 'backup.ultimo_error',
                'valor' => '',
                'tipo' => 'string',
                'nombre' => 'Backup: último error',
                'orden' => 20,
                'grupo_orden' => 20,
                'grupo' => 'Backup',
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
            'backup.ultimo_ok',
            'backup.ultimo_error',
            'backup.ultimo_detalle',
        ])->delete();
    }
};
