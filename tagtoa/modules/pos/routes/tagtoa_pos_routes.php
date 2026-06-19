<?php

/*
|--------------------------------------------------------------------------
| TAGTOA POS — Routes (coller en bas de routes/web.php)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\TaGtoaPosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('tagtoa/pos')->name('tagtoa.pos.')->group(function () {
    Route::get('/', [TaGtoaPosController::class, 'index'])->name('index');
    Route::post('/', [TaGtoaPosController::class, 'store'])->name('store');

    Route::get('/{id}/register', [TaGtoaPosController::class, 'register'])->name('register');
    Route::post('/{id}/sale', [TaGtoaPosController::class, 'sale'])->name('sale');
    Route::post('/{id}/sync', [TaGtoaPosController::class, 'sync'])->name('sync');
    Route::get('/{id}/report', [TaGtoaPosController::class, 'report'])->name('report');

    Route::get('/{id}/products', [TaGtoaPosController::class, 'products'])->name('products');
    Route::post('/{id}/products', [TaGtoaPosController::class, 'saveProducts'])->name('products.save');
    Route::post('/{id}/cash', [TaGtoaPosController::class, 'cash'])->name('cash');
});
