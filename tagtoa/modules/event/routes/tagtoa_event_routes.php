<?php

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Routes (coller en bas de routes/web.php)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\TaGtoaEventCheckinController;
use App\Http\Controllers\TaGtoaEventController;
use App\Http\Controllers\TaGtoaEventPublicController;
use Illuminate\Support\Facades\Route;

// ----- Public
Route::get('/event/{alias}', [TaGtoaEventPublicController::class, 'show'])->name('tagtoa.event.show');
Route::post('/event/{alias}/buy', [TaGtoaEventPublicController::class, 'buy'])->name('tagtoa.event.buy');
Route::get('/event/order/{reference}', [TaGtoaEventPublicController::class, 'order'])->name('tagtoa.event.order');
Route::get('/event/ticket/{code}', [TaGtoaEventPublicController::class, 'ticket'])->name('tagtoa.event.ticket');

// ----- Dashboard organisateur (auth + tenant)
Route::middleware(['web', 'auth'])->prefix('tagtoa/event')->name('tagtoa.event.dashboard.')->group(function () {
    Route::get('/', [TaGtoaEventController::class, 'index'])->name('index');
    Route::get('/create', [TaGtoaEventController::class, 'create'])->name('create');
    Route::post('/', [TaGtoaEventController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TaGtoaEventController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TaGtoaEventController::class, 'update'])->name('update');
    Route::get('/{id}/orders', [TaGtoaEventController::class, 'orders'])->name('orders');
    Route::get('/{id}/orders/export', [TaGtoaEventController::class, 'exportOrders'])->name('orders.export');

    // Scanner de check-in
    Route::get('/{id}/scanner', [TaGtoaEventCheckinController::class, 'scanner'])->name('scanner');
    Route::post('/{id}/scan', [TaGtoaEventCheckinController::class, 'scan'])->name('scan');
    Route::post('/{id}/sync', [TaGtoaEventCheckinController::class, 'sync'])->name('sync');
});
