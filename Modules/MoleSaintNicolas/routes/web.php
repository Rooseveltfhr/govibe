<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CommunityProjectController as AdminCommunityProjectController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Etablissements\EstablishmentController as AdminEstablishmentController;
use App\Http\Controllers\Admin\Etablissements\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\Histoire\HistoricalEventController as AdminHistoricalEventController;
use App\Http\Controllers\Admin\Histoire\HistoricalFigureController as AdminHistoricalFigureController;
use App\Http\Controllers\Admin\Histoire\HistoricalPeriodController as AdminHistoricalPeriodController;
use App\Http\Controllers\Admin\Histoire\HistoricalSiteController as AdminHistoricalSiteController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ProjectCommentController as AdminProjectCommentController;
use App\Http\Controllers\Admin\Territoire\CommuneController as AdminCommuneController;
use App\Http\Controllers\Admin\Territoire\SectionCommunaleController as AdminSectionCommunaleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CarteController;
use App\Http\Controllers\CentreVilleController;
use App\Http\Controllers\CommunityProjectController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\HistoireController;
use App\Http\Controllers\HistoricalSiteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TerritoireController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/histoire', [HistoireController::class, 'index'])->name('histoire.index');

Route::prefix('lieux-historiques')->name('lieux-historiques.')->group(function () {
    Route::get('/', [HistoricalSiteController::class, 'index'])->name('index');
    Route::get('/{slug}', [HistoricalSiteController::class, 'show'])->name('show');
});

Route::prefix('territoire')->name('territoire.')->group(function () {
    Route::get('/', [TerritoireController::class, 'index'])->name('index');
    Route::get('/{commune}', [TerritoireController::class, 'commune'])->name('commune');
    Route::get('/{commune}/{section}', [TerritoireController::class, 'section'])->name('section');
});

Route::get('/etablissements', [EstablishmentController::class, 'all'])->name('etablissements.index');
Route::get('/hotels', [EstablishmentController::class, 'hotels'])->name('hotels.index');
Route::get('/hotels/{slug}', [EstablishmentController::class, 'showHotel'])->name('hotels.show');
Route::get('/restaurants', [EstablishmentController::class, 'restaurants'])->name('restaurants.index');
Route::get('/restaurants/{slug}', [EstablishmentController::class, 'showRestaurant'])->name('restaurants.show');

Route::post('/etablissements/{establishment}/reservations', [BookingController::class, 'store'])->name('bookings.store');

Route::prefix('actualites')->name('actualites.')->group(function () {
    Route::get('/', [PostController::class, 'index'])->name('index');
    Route::get('/{slug}', [PostController::class, 'show'])->name('show');
});

Route::prefix('projets')->name('projets.')->group(function () {
    Route::get('/', [CommunityProjectController::class, 'index'])->name('index');
    Route::get('/{slug}', [CommunityProjectController::class, 'show'])->name('show');
    Route::post('/{slug}/commentaires', [CommunityProjectController::class, 'storeComment'])
        ->middleware('throttle:5,1')
        ->name('comments.store');
});

Route::get('/carte', [CarteController::class, 'index'])->name('carte.index');
Route::get('/centre-ville', [CentreVilleController::class, 'index'])->name('centre-ville.index');

Route::get('/a-propos', [PageController::class, 'about'])->name('pages.about');
Route::get('/mentions-legales', [PageController::class, 'legal'])->name('pages.legal');

// Admin — auth
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // Admin — zone protégée
    Route::middleware(['auth', 'role:super_admin|admin|editor|moderator'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profil', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profil', [AdminProfileController::class, 'update'])->name('profile.update');

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
            Route::resource('sites', AdminHistoricalSiteController::class)
                ->except('show')
                ->parameters(['sites' => 'site']);
        });

        Route::resource('etablissements', AdminEstablishmentController::class)
            ->except('show')
            ->parameters(['etablissements' => 'etablissement']);

        Route::resource('pages', AdminPageController::class)
            ->except('show')
            ->parameters(['pages' => 'page']);

        Route::resource('posts', AdminPostController::class)
            ->except('show')
            ->parameters(['posts' => 'post']);

        Route::resource('projets', AdminCommunityProjectController::class)
            ->except('show')
            ->parameters(['projets' => 'projet']);

        Route::prefix('projets/commentaires')->name('projets.comments.')->group(function () {
            Route::get('/', [AdminProjectCommentController::class, 'index'])->name('index');
            Route::put('/{comment}/approuver', [AdminProjectCommentController::class, 'approve'])->name('approve');
            Route::delete('/{comment}', [AdminProjectCommentController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('reservations')->name('reservations.')->group(function () {
            Route::get('/', [AdminReservationController::class, 'index'])->name('index');
            Route::put('/{reservation}', [AdminReservationController::class, 'update'])->name('update');
        });
    });
});
