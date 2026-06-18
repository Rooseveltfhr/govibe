<?php

/*
|--------------------------------------------------------------------------
| TAGTOA LINKS — Routes
|--------------------------------------------------------------------------
| COPIER ce bloc EN BAS de routes/web.php.
*/

use App\Http\Controllers\TaGtoaLinkController;
use App\Http\Controllers\TaGtoaLinkDashboardController;
use Illuminate\Support\Facades\Route;

// ----- Public : tagtoa.com/links/{alias}
Route::get('/links/{alias}', [TaGtoaLinkController::class, 'show'])->name('tagtoa.links.show');
Route::get('/links/go/{link}', [TaGtoaLinkController::class, 'go'])->name('tagtoa.links.go');

// ----- Dashboard owner (auth + tenant). Adapter le middleware au projet.
Route::middleware(['web', 'auth'])->prefix('tagtoa/links')->name('tagtoa.links.dashboard.')->group(function () {
    Route::get('/', [TaGtoaLinkDashboardController::class, 'index'])->name('index');
    Route::get('/create', [TaGtoaLinkDashboardController::class, 'create'])->name('create');
    Route::post('/', [TaGtoaLinkDashboardController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TaGtoaLinkDashboardController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TaGtoaLinkDashboardController::class, 'update'])->name('update');
    Route::delete('/{id}', [TaGtoaLinkDashboardController::class, 'destroy'])->name('destroy');
});
