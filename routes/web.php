<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormationController;
use App\Http\Controllers\Admin\InscriptionController as AdminInscriptionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\ERP\ERPAuthController;
use App\Http\Controllers\ERP\DashboardController as ERPDashboardController;
use App\Http\Controllers\ERP\CRM\ClientController;
use App\Http\Controllers\ERP\Projects\ProjectController;
use App\Http\Controllers\ERP\Finance\InvoiceController;
use App\Http\Controllers\ERP\Admin\SuperAdminController;
use App\Http\Controllers\ERP\HR\HRController;
use App\Http\Controllers\ERP\Booking\BookingController;
use App\Http\Controllers\ERP\POS\POSController;
use App\Http\Controllers\ERP\Inventory\InventoryController;
use App\Http\Controllers\ERP\Reports\ReportController;
use App\Http\Controllers\ERP\Academy\AcademyERPController;
use App\Http\Controllers\ERP\Services\ServiceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/inscription', [InscriptionController::class, 'create'])->name('inscription.create');
Route::post('/inscription', [InscriptionController::class, 'store'])->name('inscription.store');
Route::get('/inscription/qr/{inscription}', [InscriptionController::class, 'qr'])->name('inscription.qr');
Route::post('/inscription/scan', [InscriptionController::class, 'scan'])->name('inscription.scan');

// Admin auth (Academy)
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

// ═══════════════════════════════════════════════════════════
//  ERP — GOVIBE Innovation Hub
// ═══════════════════════════════════════════════════════════
Route::prefix('erp')->name('erp.')->group(function () {

    // Auth
    Route::get('/login', [ERPAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ERPAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [ERPAuthController::class, 'logout'])->name('logout');

    Route::middleware('erp')->group(function () {

        // Dashboard
        Route::get('/', [ERPDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [ERPDashboardController::class, 'index'])->name('dashboard.alt');

        // ── CRM ──────────────────────────────────────────
        Route::prefix('crm')->name('crm.')->group(function () {
            Route::resource('clients', ClientController::class);
        });

        // ── Projects ──────────────────────────────────────
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])->name('index');
            Route::get('/create', [ProjectController::class, 'create'])->name('create');
            Route::get('/kanban', [ProjectController::class, 'kanban'])->name('kanban');
            Route::post('/', [ProjectController::class, 'store'])->name('store');
            Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
            Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
            Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
            Route::patch('/{project}', [ProjectController::class, 'update']);
            Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
        });

        // ── Finance / Invoices ────────────────────────────
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
        });
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::get('/create', [InvoiceController::class, 'create'])->name('create');
            Route::post('/', [InvoiceController::class, 'store'])->name('store');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
            Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('pdf');
            Route::patch('/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('mark-paid');
        });

        // ── HR ────────────────────────────────────────────
        Route::prefix('hr')->name('hr.')->group(function () {
            Route::get('/', [HRController::class, 'index'])->name('index');
        });

        // ── Booking ───────────────────────────────────────
        Route::prefix('booking')->name('booking.')->group(function () {
            Route::get('/', [BookingController::class, 'index'])->name('index');
            Route::get('/create', [BookingController::class, 'create'])->name('create');
            Route::post('/', [BookingController::class, 'store'])->name('store');
        });

        // ── POS ───────────────────────────────────────────
        Route::prefix('pos')->name('pos.')->group(function () {
            Route::get('/', [POSController::class, 'index'])->name('index');
            Route::post('/sale', [POSController::class, 'sale'])->name('sale');
        });

        // ── Inventory ─────────────────────────────────────
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
        });

        // ── Reports ───────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
        });

        // ── Academy ERP ───────────────────────────────────
        Route::prefix('academy')->name('academy.')->group(function () {
            Route::get('/', [AcademyERPController::class, 'index'])->name('index');
        });

        // ── Services (catalogue) ──────────────────────────
        Route::prefix('services')->name('services.')->group(function () {
            Route::get('/', [ServiceController::class, 'index'])->name('index');
        });

        // ── Super Admin ───────────────────────────────────
        Route::prefix('admin')->name('admin.')->group(function () {
            // Users
            Route::get('/users', [SuperAdminController::class, 'users'])->name('users.index');
            Route::post('/users', [SuperAdminController::class, 'storeUser'])->name('users.store');
            Route::patch('/users/{user}/toggle-admin', [SuperAdminController::class, 'toggleAdmin'])->name('users.toggle-admin');
            Route::delete('/users/{user}', [SuperAdminController::class, 'destroyUser'])->name('users.destroy');

            // Business Units
            Route::get('/business-units', [SuperAdminController::class, 'businessUnits'])->name('business-units.index');
            Route::post('/business-units', [SuperAdminController::class, 'storeBusinessUnit'])->name('business-units.store');
            Route::delete('/business-units/{businessUnit}', [SuperAdminController::class, 'destroyBusinessUnit'])->name('business-units.destroy');

            // Services admin
            Route::get('/services', [SuperAdminController::class, 'services'])->name('services.index');
            Route::post('/services', [SuperAdminController::class, 'storeService'])->name('services.store');
            Route::put('/services/{service}', [SuperAdminController::class, 'updateService'])->name('services.update');
            Route::delete('/services/{service}', [SuperAdminController::class, 'destroyService'])->name('services.destroy');
            Route::post('/services/categories', [SuperAdminController::class, 'storeCategory'])->name('services.category.store');
        });
    });
});
