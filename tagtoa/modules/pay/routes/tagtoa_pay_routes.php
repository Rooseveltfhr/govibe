<?php

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — Routes
|--------------------------------------------------------------------------
| COPIER ce bloc EN BAS de routes/web.php (ne pas créer un fichier séparé
| sauf si vous l'enregistrez dans RouteServiceProvider).
|
| use App\Http\Controllers\TaGtoaPayController;
| use App\Http\Controllers\TaGtoaPayDashboardController;
*/

use App\Http\Controllers\TaGtoaPayController;
use App\Http\Controllers\TaGtoaPayDashboardController;
use Illuminate\Support\Facades\Route;

// ----- Public (pas d'auth) : tagtoa.com/pay/{alias}
Route::get('/pay/{alias}', [TaGtoaPayController::class, 'show'])->name('tagtoa.pay.show');
Route::post('/pay/{alias}/submit-proof', [TaGtoaPayController::class, 'submitProof'])
    ->name('tagtoa.pay.submit-proof');

// ----- Dashboard owner (auth + tenant). Adapter le middleware à celui du projet
// (souvent 'auth' ou un groupe 'web','auth','tenant').
Route::middleware(['web', 'auth'])->prefix('tagtoa/pay')->name('tagtoa.pay.dashboard.')->group(function () {
    Route::get('/', [TaGtoaPayDashboardController::class, 'index'])->name('index');
    Route::get('/create', [TaGtoaPayDashboardController::class, 'create'])->name('create');
    Route::post('/', [TaGtoaPayDashboardController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TaGtoaPayDashboardController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TaGtoaPayDashboardController::class, 'update'])->name('update');
    Route::delete('/{id}', [TaGtoaPayDashboardController::class, 'destroy'])->name('destroy');

    Route::get('/{id}/proofs', [TaGtoaPayDashboardController::class, 'proofs'])->name('proofs');
    Route::post('/proofs/{id}/approve', [TaGtoaPayDashboardController::class, 'approveProof'])->name('proofs.approve');
    Route::post('/proofs/{id}/reject', [TaGtoaPayDashboardController::class, 'rejectProof'])->name('proofs.reject');
});
