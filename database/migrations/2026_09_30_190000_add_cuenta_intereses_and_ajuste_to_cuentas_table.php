<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->integer('id_cuenta_intereses')->nullable()->after('habilitada');
            $table->integer('id_cuenta_ajuste')->nullable()->after('id_cuenta_intereses');

            $table->foreign('id_cuenta_intereses')
                ->references('id')
                ->on('cuentas')
                ->nullOnDelete();
            $table->foreign('id_cuenta_ajuste')
                ->references('id')
                ->on('cuentas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->dropForeign(['id_cuenta_intereses']);
            $table->dropForeign(['id_cuenta_ajuste']);
            $table->dropColumn(['id_cuenta_intereses', 'id_cuenta_ajuste']);
        });
    }
};
