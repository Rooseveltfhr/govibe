<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormationController;
use App\Http\Controllers\Admin\InscriptionController as AdminInscriptionController;
use App\Http\Controllers\InscriptionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [InscriptionController::class, 'create'])->name('inscription.create');
Route::post('/inscription', [InscriptionController::class, 'store'])->name('inscription.store');
Route::get('/inscription/qr/{inscription}', [InscriptionController::class, 'qr'])->name('inscription.qr');
Route::post('/inscription/scan', [InscriptionController::class, 'scan'])->name('inscription.scan');

// Admin auth
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Inscriptions
        Route::get('/inscriptions', [AdminInscriptionController::class, 'index'])->name('inscriptions.index');
        Route::get('/inscriptions/{inscription}', [AdminInscriptionController::class, 'show'])->name('inscriptions.show');
        Route::get('/inscriptions/{inscription}/edit', [AdminInscriptionController::class, 'edit'])->name('inscriptions.edit');
        Route::put('/inscriptions/{inscription}', [AdminInscriptionController::class, 'update'])->name('inscriptions.update');
        Route::delete('/inscriptions/{inscription}', [AdminInscriptionController::class, 'destroy'])->name('inscriptions.destroy');
        Route::get('/inscriptions/export/excel', [AdminInscriptionController::class, 'exportExcel'])->name('inscriptions.export.excel');
        Route::get('/inscriptions/export/csv', [AdminInscriptionController::class, 'exportCsv'])->name('inscriptions.export.csv');
        Route::get('/inscriptions/print/list', [AdminInscriptionController::class, 'print'])->name('inscriptions.print');
        Route::get('/inscriptions/{inscription}/attestation', [AdminInscriptionController::class, 'attestation'])->name('inscriptions.attestation');

        // Formations
        Route::resource('formations', FormationController::class);
    });
});
