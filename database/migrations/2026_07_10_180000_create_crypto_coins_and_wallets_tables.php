<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_coins', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 32)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('habilitada')->default(true);
        });

        Schema::create('crypto_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 255)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedBigInteger('id_coin')->nullable();
            $table->boolean('habilitada')->default(true);
            $table->foreign('id_coin')->references('id')->on('crypto_coins')->nullOnDelete();
            $table->index('id_coin');
        });

        if (Schema::hasTable('coin_transactions')) {
            $coins = DB::table('coin_transactions')
                ->whereNotNull('coin')
                ->where('coin', '!=', '')
                ->distinct()
                ->orderBy('coin')
                ->pluck('coin');

            foreach ($coins as $coin) {
                $codigo = strtoupper(trim((string) $coin));
                if ($codigo === '') {
                    continue;
                }
                DB::table('crypto_coins')->insertOrIgnore([
                    'codigo' => $codigo,
                    'descripcion' => $codigo,
                    'habilitada' => true,
                ]);
            }

            $coinIds = DB::table('crypto_coins')->pluck('id', 'codigo');

            $pairs = DB::table('coin_transactions')
                ->select('wallet', 'coin')
                ->whereNotNull('wallet')
                ->where('wallet', '!=', '')
                ->distinct()
                ->get();

            foreach ($pairs as $pair) {
                $wallet = trim((string) $pair->wallet);
                if ($wallet === '') {
                    continue;
                }
                $coinCodigo = strtoupper(trim((string) $pair->coin));
                $idCoin = $coinIds[$coinCodigo] ?? null;

                $exists = DB::table('crypto_wallets')->where('codigo', $wallet)->exists();
                if ($exists) {
                    if ($idCoin) {
                        DB::table('crypto_wallets')
                            ->where('codigo', $wallet)
                            ->whereNull('id_coin')
                            ->update(['id_coin' => $idCoin]);
                    }
                    continue;
                }

                DB::table('crypto_wallets')->insert([
                    'codigo' => $wallet,
                    'descripcion' => $wallet,
                    'id_coin' => $idCoin,
                    'habilitada' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_wallets');
        Schema::dropIfExists('crypto_coins');
    }
};
