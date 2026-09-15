<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\AuthController;
use Modules\Core\Http\Controllers\Admin\DashboardController;
use Modules\Core\Http\Controllers\Admin\OrderController;
use Modules\Core\Http\Controllers\Admin\SettingsController;
use Modules\Core\Http\Controllers\Admin\VoiceLibraryController;
use Modules\Core\Http\Middleware\EnsureAdmin;

/*
| Panèl administrasyon an.
|
| Pa gen enskripsyon piblik: kont yo kreye ak `php artisan govibe:admin`.
| Tout wout yo (sof konekte a) pase dèyè `EnsureAdmin`: konekte EPI is_admin.
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(EnsureAdmin::class)->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/komand', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/komand/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/komand/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::post('/komand/{order}/peman', [OrderController::class, 'storePayment'])->name('orders.payments.store');
        Route::delete('/komand/{order}/peman/{payment}', [OrderController::class, 'destroyPayment'])
            ->name('orders.payments.destroy');

        Route::get('/vwa', [VoiceLibraryController::class, 'index'])->name('voices');
        Route::post('/vwa', [VoiceLibraryController::class, 'store'])->name('voices.store');
        Route::post('/vwa/klonaj', [VoiceLibraryController::class, 'clone'])->name('voices.clone');
        Route::post('/vwa/{voice}', [VoiceLibraryController::class, 'update'])->name('voices.update');
        Route::delete('/vwa/{voice}', [VoiceLibraryController::class, 'destroy'])->name('voices.destroy');

        Route::get('/konfigirasyon', [SettingsController::class, 'index'])->name('settings');
        Route::post('/konfigirasyon', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/konfigirasyon/kle', [SettingsController::class, 'updateKey'])->name('settings.key');
    });
});
