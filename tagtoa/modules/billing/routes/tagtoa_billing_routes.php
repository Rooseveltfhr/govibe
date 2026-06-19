<?php

/*
|--------------------------------------------------------------------------
| TAGTOA BILLING — Routes (coller en bas de routes/web.php)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\TaGtoaBillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('tagtoa/billing')->name('tagtoa.billing.')->group(function () {
    Route::get('/', [TaGtoaBillingController::class, 'index'])->name('index');
    Route::put('/', [TaGtoaBillingController::class, 'update'])->name('update');
});
