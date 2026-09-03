<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormationController;
use App\Http\Controllers\Admin\InscriptionController as AdminInscriptionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\PageController;
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
use App\Http\Controllers\ERP\Academy\BootcampAdminController;
use App\Http\Controllers\ERP\Academy\InscriptionERPController;
use App\Http\Controllers\ERP\Services\ServiceController;
use App\Http\Controllers\ERP\CRM\NotificationController;
use App\Http\Controllers\ERP\CRM\ContractController;
use App\Http\Controllers\ERP\Admin\SubscriptionController;
use App\Http\Controllers\BootcampController;
use App\Http\Controllers\PartenaireController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\FicheTechniqueController;
use App\Http\Controllers\ERP\PartenaireAdminController;
use App\Http\Controllers\ERP\EvenementAdminController;
use App\Http\Controllers\ERP\PasserellePaiementController;
use App\Http\Controllers\ERP\FicheTechniqueAdminController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/coworking', [PageController::class, 'coworking'])->name('coworking');
Route::get('/startup-lab', [PageController::class, 'startupLab'])->name('startup-lab');
Route::get('/academy', [PageController::class, 'academy'])->name('academy');
Route::get('/inscription', [InscriptionController::class, 'create'])->name('inscription.create');
Route::post('/inscription', [InscriptionController::class, 'store'])->name('inscription.store');
Route::get('/inscription/qr/{inscription}', [InscriptionController::class, 'qr'])->name('inscription.qr');
Route::post('/inscription/scan', [InscriptionController::class, 'scan'])->name('inscription.scan');

// Public: Tarifs / Packages
Route::get('/tarifs', [PageController::class, 'tarifs'])->name('tarifs');

// Public: Service pages
Route::get('/call-center', [PageController::class, 'callCenter'])->name('call-center');
Route::get('/programmes', [PageController::class, 'programmes'])->name('programmes');

// Public: Événements — inscription
// /evenements/{slug} est l'URL à diffuser en publicité pour un événement.
// La confirmation vit sous /evenements/confirmation/{slug} : deux segments,
// donc aucune collision possible avec un slug d'événement.
Route::get('/evenements', [EvenementController::class, 'index'])->name('evenements.index');
Route::post('/evenements/inscription', [EvenementController::class, 'store'])->name('evenements.store');
Route::get('/evenements/confirmation/{evenement}', [EvenementController::class, 'confirmation'])->name('evenements.confirmation');
Route::get('/evenements/{evenement}', [EvenementController::class, 'show'])->name('evenements.show');

// Fiche technique — remplie par les agents chez le prospect.
// URL publique : les agents la remplissent sur leur téléphone, sans
// authentification ERP ; le champ « agent » identifie qui a rempli.
Route::get('/fiche-technique', [FicheTechniqueController::class, 'create'])->name('fiche-technique.create');
Route::post('/fiche-technique', [FicheTechniqueController::class, 'store'])->name('fiche-technique.store');
Route::get('/fiche-technique/merci/{fiche}', [FicheTechniqueController::class, 'merci'])->name('fiche-technique.merci');

// Public: Moyens de paiement
Route::view('/paiement', 'paiement')->name('paiement');

// Public: Partenaires
Route::get('/partenaires', [PartenaireController::class, 'index'])->name('partenaires');
Route::post('/partenaires', [PartenaireController::class, 'store'])->name('partenaires.store');

// GOVIBE AI Bootcamp 2026
Route::get('/bootcamp-ai-2026', [BootcampController::class, 'landing'])->name('bootcamp.landing');
Route::post('/bootcamp-ai-2026/register', [BootcampController::class, 'register'])->name('bootcamp.register');

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
            Route::get('/anniversaires', [ClientController::class, 'anniversaires'])->name('anniversaires');
            Route::post('/anniversaires/send', [ClientController::class, 'sendBirthdayManual'])->name('anniversaires.send');
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
        });

        // ── Contrats ──────────────────────────────────────
        Route::prefix('contracts')->name('contracts.')->group(function () {
            Route::get('/', [ContractController::class, 'index'])->name('index');
            Route::get('/create', [ContractController::class, 'create'])->name('create');
            Route::post('/', [ContractController::class, 'store'])->name('store');
            Route::get('/templates', [ContractController::class, 'templates'])->name('templates');
            Route::post('/templates', [ContractController::class, 'storeTemplate'])->name('templates.store');
            Route::post('/templates/seed', [ContractController::class, 'seedTemplates'])->name('templates.seed');
            Route::get('/{contract}', [ContractController::class, 'show'])->name('show');
            Route::get('/{contract}/edit', [ContractController::class, 'edit'])->name('edit');
            Route::put('/{contract}', [ContractController::class, 'update'])->name('update');
            Route::patch('/{contract}/sign', [ContractController::class, 'sign'])->name('sign');
            Route::post('/{contract}/send', [ContractController::class, 'sendByEmail'])->name('send');
            Route::delete('/{contract}', [ContractController::class, 'destroy'])->name('destroy');
        });

        // ── Plans & Abonnements ────────────────────────────
        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])->name('index');
            Route::post('/assign', [SubscriptionController::class, 'assign'])->name('assign');
            Route::patch('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
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
            Route::get('/receipt/{ref}', [POSController::class, 'receipt'])->name('receipt');
        });

        // ── Inventory ─────────────────────────────────────
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
        });

        // ── Reports ───────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/formations', [ReportController::class, 'formations'])->name('formations');
            Route::get('/bootcamp', [ReportController::class, 'bootcamp'])->name('bootcamp');
            Route::get('/reservations', [ReportController::class, 'reservations'])->name('reservations');
            Route::get('/pos', [ReportController::class, 'pos'])->name('pos');
            Route::get('/clients', [ReportController::class, 'clients'])->name('clients');
            // PDF exports
            Route::get('/pdf/global', [ReportController::class, 'pdfGlobal'])->name('pdf.global');
            Route::get('/pdf/formations', [ReportController::class, 'pdfFormations'])->name('pdf.formations');
            Route::get('/pdf/bootcamp', [ReportController::class, 'pdfBootcamp'])->name('pdf.bootcamp');
            Route::get('/pdf/reservations', [ReportController::class, 'pdfReservations'])->name('pdf.reservations');
            Route::get('/pdf/pos', [ReportController::class, 'pdfPos'])->name('pdf.pos');
            Route::get('/pdf/clients', [ReportController::class, 'pdfClients'])->name('pdf.clients');
        });

        // ── Academy ERP ───────────────────────────────────
        Route::prefix('academy')->name('academy.')->group(function () {
            Route::get('/', [AcademyERPController::class, 'index'])->name('index');

            // Inscriptions (formations classiques)
            Route::prefix('inscriptions')->name('inscriptions.')->group(function () {
                Route::get('/', [InscriptionERPController::class, 'index'])->name('index');
                Route::get('/{inscription}', [InscriptionERPController::class, 'show'])->name('show');
                Route::post('/{inscription}/presence', [InscriptionERPController::class, 'togglePresence'])->name('presence');
                Route::get('/{inscription}/attestation', [InscriptionERPController::class, 'attestation'])->name('attestation');
                Route::delete('/{inscription}', [InscriptionERPController::class, 'destroy'])->name('destroy');
            });

            // Bootcamp IA 2026
            Route::prefix('bootcamp')->name('bootcamp.')->group(function () {
                Route::get('/', [BootcampAdminController::class, 'index'])->name('index');
                Route::get('/export', [BootcampAdminController::class, 'export'])->name('export');
                Route::get('/{registration}', [BootcampAdminController::class, 'show'])->name('show');
                Route::post('/{registration}/approve', [BootcampAdminController::class, 'approve'])->name('approve');
                Route::post('/{registration}/reject', [BootcampAdminController::class, 'reject'])->name('reject');
            });
        });

        // ── Services (catalogue) ──────────────────────────
        Route::prefix('services')->name('services.')->group(function () {
            Route::get('/', [ServiceController::class, 'index'])->name('index');
        });

        // ── Partenaires ───────────────────────────────────
        Route::prefix('partenaires')->name('partenaires.')->group(function () {
            Route::get('/', [PartenaireAdminController::class, 'index'])->name('index');
            Route::patch('/{partenaire}/statut', [PartenaireAdminController::class, 'updateStatut'])->name('statut');
            // POST et non PATCH : un envoi de fichier passe en multipart.
            Route::post('/{partenaire}/vitrine', [PartenaireAdminController::class, 'updateVitrine'])->name('vitrine');
            Route::delete('/{partenaire}/logo', [PartenaireAdminController::class, 'destroyLogo'])->name('logo.destroy');
            Route::delete('/{partenaire}', [PartenaireAdminController::class, 'destroy'])->name('destroy');
        });

        // ── Événements ────────────────────────────────────
        Route::prefix('evenements')->name('evenements.')->group(function () {
            Route::get('/', [EvenementAdminController::class, 'index'])->name('index');
            Route::post('/', [EvenementAdminController::class, 'store'])->name('store');
            Route::put('/{evenement}', [EvenementAdminController::class, 'update'])->name('update');
            Route::delete('/{evenement}', [EvenementAdminController::class, 'destroy'])->name('destroy');
            Route::get('/{evenement}/reservations', [EvenementAdminController::class, 'reservations'])->name('reservations');
            Route::get('/{evenement}/export', [EvenementAdminController::class, 'exportReservations'])->name('export');
            Route::patch('/reservations/{reservation}/presence', [EvenementAdminController::class, 'togglePresence'])->name('presence');
            Route::delete('/reservations/{reservation}', [EvenementAdminController::class, 'destroyReservation'])->name('reservations.destroy');
        });

        // ── Moyens de paiement ────────────────────────────
        Route::prefix('paiements')->name('paiements.')->group(function () {
            Route::get('/', [PasserellePaiementController::class, 'index'])->name('index');
            // POST et non PUT : les envois de fichiers passent en multipart.
            Route::post('/', [PasserellePaiementController::class, 'store'])->name('store');
            Route::post('/{passerelle}', [PasserellePaiementController::class, 'update'])->name('update');
            Route::delete('/{passerelle}/fichier', [PasserellePaiementController::class, 'destroyFichier'])->name('fichier.destroy');
            Route::delete('/{passerelle}', [PasserellePaiementController::class, 'destroy'])->name('destroy');
        });

        // ── Fiches techniques (prospection) ───────────────
        Route::prefix('fiches')->name('fiches.')->group(function () {
            Route::get('/', [FicheTechniqueAdminController::class, 'index'])->name('index');
            Route::get('/export', [FicheTechniqueAdminController::class, 'export'])->name('export');
            Route::get('/{fiche}', [FicheTechniqueAdminController::class, 'show'])->name('show');
            Route::post('/{fiche}/suivi', [FicheTechniqueAdminController::class, 'storeSuivi'])->name('suivi');
            Route::patch('/{fiche}/qualification', [FicheTechniqueAdminController::class, 'updateQualification'])->name('qualification');
            Route::delete('/{fiche}', [FicheTechniqueAdminController::class, 'destroy'])->name('destroy');
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
