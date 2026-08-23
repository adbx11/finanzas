<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracion')) {
            Schema::create('configuracion', function (Blueprint $table) {
                $table->increments('id_configuracion');
                $table->string('clave', 255)->nullable()->unique('configuracion_unique');
                $table->string('valor', 255)->nullable();
                $table->string('tipo', 255)->nullable();
                $table->string('nombre', 255)->nullable();
                $table->integer('orden')->nullable();
                $table->integer('grupo_orden')->nullable();
                $table->string('grupo', 255)->nullable();
            });
        }

        $defaults = [
            [
                'clave' => 'cotizacion.url',
                'valor' => 'https://mercados.ambito.com//dolar/informal/variacion',
                'tipo' => 'string',
                'nombre' => 'URL cotización fiat (Ámbito)',
                'orden' => 10,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
            [
                'clave' => 'cotizacion.coingecko.url',
                'valor' => 'https://api.coingecko.com/api/v3/exchange_rates',
                'tipo' => 'string',
                'nombre' => 'URL CoinGecko (BTC)',
                'orden' => 20,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
            [
                'clave' => 'cotizacion.btcusd',
                'valor' => '0',
                'tipo' => 'decimal',
                'nombre' => 'BTC/USD (última)',
                'orden' => 30,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
            [
                'clave' => 'cotizacion.btcars',
                'valor' => '0',
                'tipo' => 'decimal',
                'nombre' => 'BTC/ARS (última)',
                'orden' => 40,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
            [
                'clave' => 'cotizacion.job.hora_desde',
                'valor' => '9',
                'tipo' => 'int',
                'nombre' => 'Job: hora desde (0-23)',
                'orden' => 50,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
            [
                'clave' => 'cotizacion.http.verify_ssl',
                'valor' => '1',
                'tipo' => 'boolean',
                'nombre' => 'Verificar SSL al obtener cotizaciones (0 en Windows sin cacert)',
                'orden' => 70,
                'grupo_orden' => 10,
                'grupo' => 'Cotizaciones',
            ],
        ];

        foreach ($defaults as $row) {
            $exists = DB::table('configuracion')->where('clave', $row['clave'])->exists();
            if (! $exists) {
                DB::table('configuracion')->insert($row);
            }
        }
    }

    public function down(): void
    {
        // No drop: tabla legacy reutilizada.
    }
};
