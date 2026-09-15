<?php

use Illuminate\Support\Facades\Route;
use Modules\Agents\Http\Controllers\LandingController;
use Modules\Agents\Http\Controllers\OrderController;
use Modules\Agents\Http\Controllers\SupportController;

// Paj akèy la: sa yon vizitè wè anvan li konnen anyen sou nou.
Route::get('/', [LandingController::class, 'index'])->name('home');

// Sipò a se yon ajan tou — menm runtime ak sa nou vann yo. Si li pa mache
// isit la, li p ap mache lakay yon machann non plis.
Route::post('/sipo', [SupportController::class, 'ask'])->name('support.ask');

// Kòmand: pou moun ki vle yon ekspè fè travay la pou yo.
Route::get('/komande/{sector?}', [OrderController::class, 'create'])->name('orders.create');
Route::post('/komande', [OrderController::class, 'store'])->name('orders.store');
Route::get('/komande/{reference}/konfimasyon', [OrderController::class, 'show'])
    ->where('reference', 'LV-[0-9]{8}-[A-Z0-9]{4}')
    ->name('orders.show');
