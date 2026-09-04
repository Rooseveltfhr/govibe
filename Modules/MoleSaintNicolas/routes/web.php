<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Etablissements\EstablishmentController as AdminEstablishmentController;
use App\Http\Controllers\Admin\Etablissements\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\Histoire\HistoricalEventController as AdminHistoricalEventController;
use App\Http\Controllers\Admin\Histoire\HistoricalFigureController as AdminHistoricalFigureController;
use App\Http\Controllers\Admin\Histoire\HistoricalPeriodController as AdminHistoricalPeriodController;
use App\Http\Controllers\Admin\Territoire\CommuneController as AdminCommuneController;
use App\Http\Controllers\Admin\Territoire\SectionCommunaleController as AdminSectionCommunaleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\EstablishmentController;
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

Route::get('/hotels', [EstablishmentController::class, 'hotels'])->name('hotels.index');
Route::get('/hotels/{slug}', [EstablishmentController::class, 'showHotel'])->name('hotels.show');
Route::get('/restaurants', [EstablishmentController::class, 'restaurants'])->name('restaurants.index');
Route::get('/restaurants/{slug}', [EstablishmentController::class, 'showRestaurant'])->name('restaurants.show');

Route::post('/etablissements/{establishment}/reservations', [BookingController::class, 'store'])->name('bookings.store');

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

        Route::resource('etablissements', AdminEstablishmentController::class)
            ->except('show')
            ->parameters(['etablissements' => 'etablissement']);

        Route::prefix('reservations')->name('reservations.')->group(function () {
            Route::get('/', [AdminReservationController::class, 'index'])->name('index');
            Route::put('/{reservation}', [AdminReservationController::class, 'update'])->name('update');
        });
    });
});
