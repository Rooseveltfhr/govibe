<?php

use Illuminate\Support\Facades\Route;
use Modules\Tagtoa\App\Http\Controllers\Billing\BillingController;
use Modules\Tagtoa\App\Http\Controllers\Event\CheckinController as EventCheckin;
use Modules\Tagtoa\App\Http\Controllers\Event\DashboardController as EventDashboard;
use Modules\Tagtoa\App\Http\Controllers\Event\PublicController as EventPublic;
use Modules\Tagtoa\App\Http\Controllers\Hub\HubController;
use Modules\Tagtoa\App\Http\Controllers\LandingController;
use Modules\Tagtoa\App\Http\Controllers\Links\DashboardController as LinksDashboard;
use Modules\Tagtoa\App\Http\Controllers\Links\PublicController as LinksPublic;
use Modules\Tagtoa\App\Http\Controllers\Loyalty\DashboardController as LoyaltyDashboard;
use Modules\Tagtoa\App\Http\Controllers\Loyalty\PublicController as LoyaltyPublic;
use Modules\Tagtoa\App\Http\Controllers\Menu\DashboardController as MenuDashboard;
use Modules\Tagtoa\App\Http\Controllers\Menu\PublicController as MenuPublic;
use Modules\Tagtoa\App\Http\Controllers\Pay\DashboardController as PayDashboard;
use Modules\Tagtoa\App\Http\Controllers\Pay\PublicController as PayPublic;
use Modules\Tagtoa\App\Http\Controllers\Pos\PosController;
use Modules\Tagtoa\App\Http\Controllers\Site\DashboardController as SiteDashboard;
use Modules\Tagtoa\App\Http\Controllers\Site\PublicController as SitePublic;

/*
|--------------------------------------------------------------------------
| TAGTOA — Web routes (auto-enregistrées par RouteServiceProvider)
|--------------------------------------------------------------------------
| Adapter le middleware 'auth' au besoin (groupe back-office de Biztap).
*/

// ---------- PUBLIC (NFC / QR, pas d'auth) ----------
// Page d'accueil TAGTOA à la racine (remplace l'accueil par défaut).
Route::get('/', [LandingController::class, 'index'])->name('tagtoa.landing');
Route::get('/pay/{alias}', [PayPublic::class, 'show'])->name('tagtoa.pay.show');
Route::post('/pay/{alias}/submit-proof', [PayPublic::class, 'submitProof'])->name('tagtoa.pay.submit-proof');
Route::get('/pay/{alias}/checkout/{method}', [PayPublic::class, 'checkout'])->name('tagtoa.pay.checkout');
Route::get('/loyalty/card/{token}', [LoyaltyPublic::class, 'show'])->name('tagtoa.loyalty.card');
Route::get('/links/{alias}', [LinksPublic::class, 'show'])->name('tagtoa.links.show');
Route::get('/links/go/{link}', [LinksPublic::class, 'go'])->name('tagtoa.links.go');
Route::get('/site/{alias}', [SitePublic::class, 'show'])->name('tagtoa.site.show');
Route::get('/menu/{alias}', [MenuPublic::class, 'show'])->name('tagtoa.menu.show');
Route::post('/menu/{alias}/order', [MenuPublic::class, 'order'])->name('tagtoa.menu.order');
Route::get('/event/{alias}', [EventPublic::class, 'show'])->name('tagtoa.event.show');
Route::post('/event/{alias}/buy', [EventPublic::class, 'buy'])->name('tagtoa.event.buy');
Route::get('/event/order/{reference}', [EventPublic::class, 'order'])->name('tagtoa.event.order');
Route::get('/event/ticket/{code}', [EventPublic::class, 'ticket'])->name('tagtoa.event.ticket');

// ---------- DASHBOARD (back-office marchand) ----------
// Middleware aligné sur le back-office Biztap (confirmé dans routes/web.php) :
// auth + valid.user + role:admin + multi_tenant (initialise le tenant courant
// pour getLogInTenantId()). Retirer/adapter si votre groupe diffère.
Route::middleware(['auth', 'valid.user', 'role:admin|super_admin', 'multi_tenant'])->prefix('tagtoa')->group(function () {

    // Accueil sur /tagtoa/home (le segment unique /tagtoa entre en conflit avec
    // la route vcard {alias} de Biztap). /tagtoa redirige vers /tagtoa/home.
    Route::get('/', fn () => redirect('/tagtoa/home'));
    Route::get('/home', [HubController::class, 'index'])->name('tagtoa.hub');

    // PAY
    Route::prefix('pay')->name('tagtoa.pay.dashboard.')->group(function () {
        Route::get('/', [PayDashboard::class, 'index'])->name('index');
        Route::get('/create', [PayDashboard::class, 'create'])->name('create');
        Route::post('/', [PayDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PayDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [PayDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [PayDashboard::class, 'destroy'])->name('destroy');
        Route::get('/{id}/proofs', [PayDashboard::class, 'proofs'])->name('proofs');
        Route::post('/proofs/{id}/approve', [PayDashboard::class, 'approveProof'])->name('proofs.approve');
        Route::post('/proofs/{id}/reject', [PayDashboard::class, 'rejectProof'])->name('proofs.reject');
    });

    // SITE (création de site web par abonnement)
    Route::prefix('site')->name('tagtoa.site.dashboard.')->group(function () {
        Route::get('/', [SiteDashboard::class, 'index'])->name('index');
        Route::get('/create', [SiteDashboard::class, 'create'])->name('create');
        Route::post('/', [SiteDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [SiteDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [SiteDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [SiteDashboard::class, 'destroy'])->name('destroy');
    });

    // MENU (restaurant, club, lounge, hôtel, bar, café…)
    Route::prefix('menu')->name('tagtoa.menu.dashboard.')->group(function () {
        Route::get('/', [MenuDashboard::class, 'index'])->name('index');
        Route::get('/create', [MenuDashboard::class, 'create'])->name('create');
        Route::post('/', [MenuDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [MenuDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [MenuDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [MenuDashboard::class, 'destroy'])->name('destroy');
        Route::get('/{id}/orders', [MenuDashboard::class, 'orders'])->name('orders');
        Route::post('/orders/{order}/status', [MenuDashboard::class, 'setStatus'])->name('orders.status');
        Route::post('/orders/{order}/paid', [MenuDashboard::class, 'markPaid'])->name('orders.paid');
    });

    // LOYALTY
    Route::prefix('loyalty')->name('tagtoa.loyalty.dashboard.')->group(function () {
        Route::get('/', [LoyaltyDashboard::class, 'index'])->name('index');
        Route::get('/create', [LoyaltyDashboard::class, 'create'])->name('create');
        Route::post('/', [LoyaltyDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [LoyaltyDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [LoyaltyDashboard::class, 'update'])->name('update');
        Route::get('/{id}/cards', [LoyaltyDashboard::class, 'cards'])->name('cards');
        Route::post('/{id}/cards', [LoyaltyDashboard::class, 'issueCard'])->name('cards.issue');
        Route::post('/cards/{id}/top-up', [LoyaltyDashboard::class, 'topUp'])->name('cards.topup');
        Route::post('/cards/{id}/redeem', [LoyaltyDashboard::class, 'redeem'])->name('cards.redeem');
        Route::post('/{id}/rewards', [LoyaltyDashboard::class, 'storeReward'])->name('rewards.store');
    });

    // LINKS
    Route::prefix('links')->name('tagtoa.links.dashboard.')->group(function () {
        Route::get('/', [LinksDashboard::class, 'index'])->name('index');
        Route::get('/create', [LinksDashboard::class, 'create'])->name('create');
        Route::post('/', [LinksDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [LinksDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [LinksDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [LinksDashboard::class, 'destroy'])->name('destroy');
    });

    // EVENT
    Route::prefix('event')->name('tagtoa.event.dashboard.')->group(function () {
        Route::get('/', [EventDashboard::class, 'index'])->name('index');
        Route::get('/create', [EventDashboard::class, 'create'])->name('create');
        Route::post('/', [EventDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [EventDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [EventDashboard::class, 'update'])->name('update');
        Route::get('/{id}/orders', [EventDashboard::class, 'orders'])->name('orders');
        Route::get('/{id}/orders/export', [EventDashboard::class, 'exportOrders'])->name('orders.export');
        Route::get('/{id}/scanner', [EventCheckin::class, 'scanner'])->name('scanner');
        Route::post('/{id}/scan', [EventCheckin::class, 'scan'])->name('scan');
        Route::post('/{id}/sync', [EventCheckin::class, 'sync'])->name('sync');
    });

    // POS
    Route::prefix('pos')->name('tagtoa.pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/', [PosController::class, 'store'])->name('store');
        Route::get('/{id}/register', [PosController::class, 'register'])->name('register');
        Route::post('/{id}/sale', [PosController::class, 'sale'])->name('sale');
        Route::post('/{id}/sync', [PosController::class, 'sync'])->name('sync');
        Route::get('/{id}/report', [PosController::class, 'report'])->name('report');
        Route::get('/{id}/products', [PosController::class, 'products'])->name('products');
        Route::post('/{id}/products', [PosController::class, 'saveProducts'])->name('products.save');
    });

    // BILLING
    Route::get('/billing', [BillingController::class, 'index'])->name('tagtoa.billing.index');
    Route::put('/billing', [BillingController::class, 'update'])->name('tagtoa.billing.update');
    Route::post('/billing/settle', [BillingController::class, 'settle'])->name('tagtoa.billing.settle');
    Route::get('/billing/export', [BillingController::class, 'export'])->name('tagtoa.billing.export');
});
