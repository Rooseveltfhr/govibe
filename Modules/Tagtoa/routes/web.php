<?php

use Illuminate\Support\Facades\Route;
use Modules\Tagtoa\App\Http\Controllers\Billing\BillingController;
use Modules\Tagtoa\App\Http\Controllers\Hub\HubController;
use Modules\Tagtoa\App\Http\Controllers\Pay\DashboardController as PayDashboard;
use Modules\Tagtoa\App\Http\Controllers\Pay\PublicController as PayPublic;

/*
|--------------------------------------------------------------------------
| TAGTOA — Web routes (auto-enregistrées par RouteServiceProvider)
|--------------------------------------------------------------------------
| Adapter le middleware 'auth' au besoin (groupe back-office de Biztap).
*/

// ---------- PUBLIC (NFC / QR, pas d'auth) ----------
Route::get('/pay/{alias}', [PayPublic::class, 'show'])->name('tagtoa.pay.show');
Route::post('/pay/{alias}/submit-proof', [PayPublic::class, 'submitProof'])->name('tagtoa.pay.submit-proof');

// ---------- DASHBOARD (auth) ----------
Route::middleware(['auth'])->prefix('tagtoa')->group(function () {

    Route::get('/', [HubController::class, 'index'])->name('tagtoa.hub');

    // PAY
    Route::prefix('pay')->name('tagtoa.pay.dashboard.')->group(function () {
        Route::get('/', [PayDashboard::class, 'index'])->name('index');
        Route::get('/create', [PayDashboard::class, 'create'])->name('create');
        Route::post('/', [PayDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PayDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [PayDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [PayDashboard::class, 'destroy'])->name('destroy');
        Route::get('/{id}/proofs', [PayDashboard::class, 'proofs'])->name('proofs');
        Route::post('/proofs/{id}/approve', [PayDashboard::class, 'approveProof'])->name('proofs.approve');
        Route::post('/proofs/{id}/reject', [PayDashboard::class, 'rejectProof'])->name('proofs.reject');
    });

    // BILLING
    Route::get('/billing', [BillingController::class, 'index'])->name('tagtoa.billing.index');
    Route::put('/billing', [BillingController::class, 'update'])->name('tagtoa.billing.update');
});
