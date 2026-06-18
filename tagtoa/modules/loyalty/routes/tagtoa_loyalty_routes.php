<?php

/*
|--------------------------------------------------------------------------
| TAGTOA LOYALTY — Routes
|--------------------------------------------------------------------------
| COPIER ce bloc EN BAS de routes/web.php.
*/

use App\Http\Controllers\TaGtoaLoyaltyController;
use App\Http\Controllers\TaGtoaLoyaltyDashboardController;
use Illuminate\Support\Facades\Route;

// ----- Public (NFC tap / QR) : tagtoa.com/loyalty/card/{token}
Route::get('/loyalty/card/{token}', [TaGtoaLoyaltyController::class, 'show'])
    ->name('tagtoa.loyalty.card');

// ----- Dashboard owner (auth + tenant). Adapter le middleware au projet.
Route::middleware(['web', 'auth'])->prefix('tagtoa/loyalty')->name('tagtoa.loyalty.dashboard.')->group(function () {
    Route::get('/', [TaGtoaLoyaltyDashboardController::class, 'index'])->name('index');
    Route::get('/create', [TaGtoaLoyaltyDashboardController::class, 'create'])->name('create');
    Route::post('/', [TaGtoaLoyaltyDashboardController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TaGtoaLoyaltyDashboardController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TaGtoaLoyaltyDashboardController::class, 'update'])->name('update');

    // Cards
    Route::get('/{id}/cards', [TaGtoaLoyaltyDashboardController::class, 'cards'])->name('cards');
    Route::post('/{id}/cards', [TaGtoaLoyaltyDashboardController::class, 'issueCard'])->name('cards.issue');
    Route::post('/cards/{id}/top-up', [TaGtoaLoyaltyDashboardController::class, 'topUp'])->name('cards.topup');
    Route::post('/cards/{id}/redeem', [TaGtoaLoyaltyDashboardController::class, 'redeem'])->name('cards.redeem');
    Route::post('/cards/{id}/status', [TaGtoaLoyaltyDashboardController::class, 'setStatus'])->name('cards.status');

    // Rewards
    Route::post('/{id}/rewards', [TaGtoaLoyaltyDashboardController::class, 'storeReward'])->name('rewards.store');
    Route::delete('/rewards/{id}', [TaGtoaLoyaltyDashboardController::class, 'destroyReward'])->name('rewards.destroy');
});
