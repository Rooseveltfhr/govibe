<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');

// Admin — auth
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // Admin — zone protégée (Phase 1 : dashboard vide, les modules CRUD arrivent Phase 2+)
    Route::middleware(['auth', 'role:super_admin|admin|editor|moderator'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });
});
