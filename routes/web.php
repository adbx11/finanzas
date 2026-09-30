<?php

use App\Http\Controllers\AsientoController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\CoinTransactionController;
use App\Http\Controllers\ConciliacionController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\CryptoCoinController;
use App\Http\Controllers\CryptoReportController;
use App\Http\Controllers\CryptoWalletController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvolucionCuentaController;
use App\Http\Controllers\EvolucionPatrimonialController;
use App\Http\Controllers\GastosController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\InteresesController;
use App\Http\Controllers\MayorController;
use App\Http\Controllers\MonedaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VencimientosTcController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/finanzas', [DashboardController::class, 'finanzas'])->name('dashboard.finanzas');
    Route::get('/dashboard/saldos', [DashboardController::class, 'saldos'])->name('dashboard.saldos');
    Route::get('/dashboard/distribucion', [DashboardController::class, 'distribucion'])->name('dashboard.distribucion');
    Route::get('/dashboard/crypto', [DashboardController::class, 'crypto'])->name('dashboard.crypto');
    Route::get('/dashboard/crypto/wallets', [DashboardController::class, 'cryptoWallets'])->name('dashboard.crypto.wallets');

    Route::resource('monedas', MonedaController::class)->except(['show', 'create', 'edit']);
    Route::resource('crypto-coins', CryptoCoinController::class)->except(['show', 'create', 'edit']);
    Route::resource('crypto-wallets', CryptoWalletController::class)->except(['show', 'create', 'edit']);
    Route::resource('configuracion', ConfiguracionController::class)->except(['show', 'create', 'edit']);
    Route::resource('cuentas', CuentaController::class)->except(['show', 'create', 'edit']);
    Route::get('/api/cuentas/options', [CuentaController::class, 'options'])->name('cuentas.options');

    Route::middleware(['role:admin'])->group(function () {
        Route::resource('usuarios', UserController::class)->except(['show', 'create', 'edit']);
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup/run', [BackupController::class, 'run'])->name('backup.run');
    });

    Route::resource('ingresos', IngresoController::class)->except(['show']);
    Route::get('/api/ingresos/cotizacion', [IngresoController::class, 'cotizacion'])->name('ingresos.cotizacion');

    Route::resource('pagos', PagoController::class)->except(['show']);
    Route::get('/api/pagos/cotizacion', [PagoController::class, 'cotizacion'])->name('pagos.cotizacion');

    Route::resource('asientos', AsientoController::class)->except(['show']);
    Route::get('/api/asientos/cotizacion', [AsientoController::class, 'cotizacion'])->name('asientos.cotizacion');

    Route::get('/tarjetas', [TarjetaController::class, 'index'])->name('tarjetas.index');
    Route::post('/tarjetas/liquidar', [TarjetaController::class, 'liquidar'])->name('tarjetas.liquidar');

    Route::get('/cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::post('/cotizaciones', [CotizacionController::class, 'store'])->name('cotizaciones.store');
    Route::post('/cotizaciones/fetch', [CotizacionController::class, 'fetch'])->name('cotizaciones.fetch');

    Route::resource('crypto', CoinTransactionController::class)->except(['show', 'create', 'edit']);

    Route::get('/informes/balance', [BalanceController::class, 'index'])->name('informes.balance');
    Route::get('/informes/mayor', [MayorController::class, 'index'])->name('informes.mayor');
    Route::get('/informes/gastos', [GastosController::class, 'index'])->name('informes.gastos');
    Route::get('/informes/vencimientos-tc', [VencimientosTcController::class, 'index'])->name('informes.vencimientos-tc');
    Route::get('/informes/intereses', [InteresesController::class, 'index'])->name('informes.intereses');
    Route::get('/informes/evolucion-patrimonial', [EvolucionPatrimonialController::class, 'index'])->name('informes.evolucion-patrimonial');
    Route::get('/informes/evolucion-cuenta', [EvolucionCuentaController::class, 'index'])->name('informes.evolucion-cuenta');
    Route::get('/informes/crypto', [CryptoReportController::class, 'index'])->name('informes.crypto');

    Route::get('/conciliacion', [ConciliacionController::class, 'index'])->name('conciliacion.index');
    Route::post('/conciliacion', [ConciliacionController::class, 'store'])->name('conciliacion.store');
    Route::get('/conciliacion/asiento-intereses', [ConciliacionController::class, 'asientoIntereses'])->name('conciliacion.asiento-intereses');
    Route::get('/conciliacion/asiento-ajuste', [ConciliacionController::class, 'asientoAjuste'])->name('conciliacion.asiento-ajuste');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
