<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Histoire\HistoricalEventController as AdminHistoricalEventController;
use App\Http\Controllers\Admin\Histoire\HistoricalFigureController as AdminHistoricalFigureController;
use App\Http\Controllers\Admin\Histoire\HistoricalPeriodController as AdminHistoricalPeriodController;
use App\Http\Controllers\Admin\Territoire\CommuneController as AdminCommuneController;
use App\Http\Controllers\Admin\Territoire\SectionCommunaleController as AdminSectionCommunaleController;
use App\Http\Controllers\HistoireController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TerritoireController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/histoire', [HistoireController::class, 'index'])->name('histoire.index');

Route::prefix('territoire')->name('territoire.')->group(function () {
    Route::get('/', [TerritoireController::class, 'index'])->name('index');
    Route::get('/{commune}', [TerritoireController::class, 'commune'])->name('commune');
    Route::get('/{commune}/{section}', [TerritoireController::class, 'section'])->name('section');
});

// Admin — auth
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // Admin — zone protégée
    Route::middleware(['auth', 'role:super_admin|admin|editor|moderator'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('territoire')->name('territoire.')->group(function () {
            Route::resource('communes', AdminCommuneController::class)
                ->except('show')
                ->parameters(['communes' => 'commune']);
            Route::resource('sections', AdminSectionCommunaleController::class)
                ->except('show')
                ->parameters(['sections' => 'section']);
        });

        Route::prefix('histoire')->name('histoire.')->group(function () {
            Route::resource('periods', AdminHistoricalPeriodController::class)
                ->except('show')
                ->parameters(['periods' => 'period']);
            Route::resource('events', AdminHistoricalEventController::class)
                ->except('show')
                ->parameters(['events' => 'event']);
            Route::resource('figures', AdminHistoricalFigureController::class)
                ->except('show')
                ->parameters(['figures' => 'figure']);
        });
    });
});
