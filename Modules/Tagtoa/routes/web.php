<?php

use Illuminate\Support\Facades\Route;
use Modules\Tagtoa\App\Http\Controllers\Billing\BillingController;
use Modules\Tagtoa\App\Http\Controllers\Booking\DashboardController as BookingDashboard;
use Modules\Tagtoa\App\Http\Controllers\Booking\PublicController as BookingPublic;
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
// La landing TAGTOA remplace l'accueil Biztap sur `/`. On CONSERVE le nom `home`
// (un seul route sur `/`) pour ne pas casser les `route('home')` du back-office
// Biztap (ex. page de login qui plantait avec « Route [home] not defined »).
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/pay/{alias}', [PayPublic::class, 'show'])->name('tagtoa.pay.show');
Route::get('/pay/{alias}/checkout/{method}', [PayPublic::class, 'checkout'])->name('tagtoa.pay.checkout');
// Paiement en ligne via passerelle API (MonCash…). Public.
Route::get('/pay/result', [\Modules\Tagtoa\App\Http\Controllers\Pay\CheckoutController::class, 'result'])->name('tagtoa.pay.result');
Route::get('/pay/checkout/{gateway}/{type}/{orderId}', [\Modules\Tagtoa\App\Http\Controllers\Pay\CheckoutController::class, 'start'])->middleware('throttle:30,1')->name('tagtoa.pay.online.start');
Route::get('/pay/{gateway}/return', [\Modules\Tagtoa\App\Http\Controllers\Pay\CheckoutController::class, 'return'])->name('tagtoa.pay.online.return');
Route::post('/pay/{gateway}/webhook', [\Modules\Tagtoa\App\Http\Controllers\Pay\CheckoutController::class, 'webhook'])->middleware('throttle:60,1')->name('tagtoa.pay.online.webhook');
Route::get('/loyalty/card/{token}', [LoyaltyPublic::class, 'show'])->name('tagtoa.loyalty.card');
Route::get('/links/{alias}', [LinksPublic::class, 'show'])->name('tagtoa.links.show');
Route::get('/links/go/{link}', [LinksPublic::class, 'go'])->name('tagtoa.links.go');
Route::get('/site/{alias}', [SitePublic::class, 'show'])->name('tagtoa.site.show');
Route::get('/menu/{alias}', [MenuPublic::class, 'show'])->name('tagtoa.menu.show');
Route::get('/store/{alias}', [\Modules\Tagtoa\App\Http\Controllers\Store\PublicController::class, 'show'])->name('tagtoa.store.show');
Route::get('/event/{alias}', [EventPublic::class, 'show'])->name('tagtoa.event.show');
Route::get('/event/order/{reference}', [EventPublic::class, 'order'])->name('tagtoa.event.order');
Route::get('/event/ticket/{code}', [EventPublic::class, 'ticket'])->name('tagtoa.event.ticket');
Route::get('/event/wallet/receipt/{reference}', [EventPublic::class, 'walletReceipt'])->name('tagtoa.event.wallet.receipt');
Route::get('/book/{alias}', [BookingPublic::class, 'show'])->name('tagtoa.booking.show');

// Écritures publiques : rate-limit (anti-spam / anti-DoS / anti-épuisement disque).
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/pay/{alias}/submit-proof', [PayPublic::class, 'submitProof'])->name('tagtoa.pay.submit-proof');
    Route::post('/menu/{alias}/order', [MenuPublic::class, 'order'])->name('tagtoa.menu.order');
    Route::post('/event/{alias}/buy', [EventPublic::class, 'buy'])->name('tagtoa.event.buy');
    Route::post('/book/{alias}/reserve', [BookingPublic::class, 'reserve'])->name('tagtoa.booking.reserve');
    Route::post('/reviews', [\Modules\Tagtoa\App\Http\Controllers\Review\PublicController::class, 'store'])->name('tagtoa.reviews.store');
    Route::post('/store/{alias}/order', [\Modules\Tagtoa\App\Http\Controllers\Store\PublicController::class, 'order'])->name('tagtoa.store.order');
});

// Terminal STAFF terrain (auth par PIN scopée événement — pas de login Laravel).
// Rate-limit plus large : le check-in aux portes est intense mais borné par appareil.
Route::prefix('event/staff/{alias}')->name('tagtoa.event.staff.')->middleware('throttle:120,1')->group(function () {
    $staffTerminal = \Modules\Tagtoa\App\Http\Controllers\Event\StaffTerminalController::class;
    Route::get('/', [$staffTerminal, 'terminal'])->name('terminal');
    Route::post('/login', [$staffTerminal, 'login'])->name('login');
    Route::post('/logout', [$staffTerminal, 'logout'])->name('logout');
    Route::post('/checkin', [$staffTerminal, 'checkin'])->name('checkin');
    Route::post('/sync', [$staffTerminal, 'sync'])->name('sync');
    Route::post('/sell', [$staffTerminal, 'sell'])->name('sell');
    Route::post('/pickup', [$staffTerminal, 'pickup'])->name('pickup');
});

// ---------- DASHBOARD (back-office marchand) ----------
// Middleware aligné sur le back-office Biztap (confirmé dans routes/web.php) :
// auth + valid.user + role:admin + multi_tenant (initialise le tenant courant
// pour getLogInTenantId()). Retirer/adapter si votre groupe diffère.
Route::middleware(['auth', 'valid.user', 'role:admin|super_admin', 'multi_tenant'])->prefix('tagtoa')->group(function () {

    // Accueil sur /tagtoa/home (le segment unique /tagtoa entre en conflit avec
    // la route vcard {alias} de Biztap). /tagtoa redirige vers /tagtoa/home.
    Route::get('/', fn () => redirect('/tagtoa/home'));
    Route::get('/home', [HubController::class, 'index'])->name('tagtoa.hub');

    // Onboarding « Commencer » : première page en 30 secondes.
    Route::get('/start', [\Modules\Tagtoa\App\Http\Controllers\Hub\OnboardingController::class, 'index'])->name('tagtoa.start');
    Route::post('/start', [\Modules\Tagtoa\App\Http\Controllers\Hub\OnboardingController::class, 'store'])->name('tagtoa.start.store');

    // PAY
    Route::prefix('pay')->name('tagtoa.pay.dashboard.')->group(function () {
        Route::get('/', [PayDashboard::class, 'index'])->name('index');
        Route::get('/create', [PayDashboard::class, 'create'])->name('create');
        Route::post('/', [PayDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PayDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [PayDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [PayDashboard::class, 'destroy'])->name('destroy');
        Route::get('/{id}/proofs', [PayDashboard::class, 'proofs'])->name('proofs');
        Route::get('/proofs/{id}/image', [PayDashboard::class, 'proofImage'])->name('proof.image');
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

    // STORE (boutique en ligne / e-commerce)
    Route::prefix('store')->name('tagtoa.store.dashboard.')->group(function () {
        $storeDash = \Modules\Tagtoa\App\Http\Controllers\Store\DashboardController::class;
        Route::get('/', [$storeDash, 'index'])->name('index');
        Route::get('/create', [$storeDash, 'create'])->name('create');
        Route::post('/', [$storeDash, 'store'])->name('store');
        Route::get('/{id}/edit', [$storeDash, 'edit'])->name('edit');
        Route::put('/{id}', [$storeDash, 'update'])->name('update');
        Route::delete('/{id}', [$storeDash, 'destroy'])->name('destroy');
        Route::get('/{id}/orders', [$storeDash, 'orders'])->name('orders');
        Route::post('/orders/{orderId}/status', [$storeDash, 'setStatus'])->name('orders.status');
        Route::post('/orders/{orderId}/paid', [$storeDash, 'markPaid'])->name('orders.paid');
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
        Route::post('/{id}/orders/{orderId}/paid', [EventDashboard::class, 'markOrderPaid'])->name('orders.paid');
        Route::get('/{id}/scanner', [EventCheckin::class, 'scanner'])->name('scanner');
        Route::post('/{id}/scan', [EventCheckin::class, 'scan'])->name('scan');
        Route::post('/{id}/scan-nfc', [EventCheckin::class, 'scanNfc'])->name('scan.nfc');
        Route::post('/{id}/sync', [EventCheckin::class, 'sync'])->name('sync');
        Route::get('/{id}/checkin-report', [EventCheckin::class, 'report'])->name('checkin.report');
        Route::get('/{id}/checkin-stats', [EventCheckin::class, 'stats'])->name('checkin.stats');
        Route::get('/{id}/badges', [EventCheckin::class, 'badges'])->name('badges');
        // WALLET closed-loop (double-entry)
        Route::get('/{id}/wallet', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'index'])->name('wallet');
        Route::get('/{id}/wallet/terminal', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'terminal'])->name('wallet.terminal');
        Route::post('/{id}/wallet/vendor', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'addVendor'])->name('wallet.vendor');
        Route::post('/{id}/wallet/tag', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'issueTag'])->name('wallet.tag');
        Route::post('/{id}/wallet/encode', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'encode'])->name('wallet.encode');
        Route::get('/{id}/wallet/mass-encode', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'massEncode'])->name('wallet.mass-encode');
        Route::post('/{id}/wallet/encode-json', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'encodeJson'])->name('wallet.encode-json');
        Route::post('/{id}/wallet/settings', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'settings'])->name('wallet.settings');
        Route::post('/{id}/wallet/topup', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'topUp'])->name('wallet.topup');
        Route::post('/{id}/wallet/payout', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'payout'])->name('wallet.payout');
        Route::post('/{id}/wallet/resolve', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'resolve'])->name('wallet.resolve');
        Route::post('/{id}/wallet/charge', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'charge'])->name('wallet.charge');
        Route::get('/{id}/wallet/export', [\Modules\Tagtoa\App\Http\Controllers\Event\WalletController::class, 'export'])->name('wallet.export');
        // STAFF terrain (création/PIN par l'organisateur uniquement)
        Route::get('/{id}/staff', [\Modules\Tagtoa\App\Http\Controllers\Event\StaffController::class, 'index'])->name('staff');
        Route::post('/{id}/staff', [\Modules\Tagtoa\App\Http\Controllers\Event\StaffController::class, 'store'])->name('staff.store');
        Route::post('/{id}/staff/{staffId}/toggle', [\Modules\Tagtoa\App\Http\Controllers\Event\StaffController::class, 'toggle'])->name('staff.toggle');
        Route::post('/{id}/staff/{staffId}/pin', [\Modules\Tagtoa\App\Http\Controllers\Event\StaffController::class, 'resetPin'])->name('staff.pin');
        Route::post('/{id}/staff/conflicts/{conflictId}/resolve', [\Modules\Tagtoa\App\Http\Controllers\Event\StaffController::class, 'resolveConflict'])->name('staff.conflict.resolve');
        Route::get('/{id}/staff/export', [\Modules\Tagtoa\App\Http\Controllers\Event\StaffController::class, 'export'])->name('staff.export');
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
        // PWA (installable + offline)
        Route::get('/sw.js', [PosController::class, 'serviceWorker'])->name('sw');
        Route::get('/icon.svg', [PosController::class, 'icon'])->name('icon');
        Route::get('/{id}/app.webmanifest', [PosController::class, 'manifest'])->name('manifest');
    });

    // BOOKING (rendez-vous)
    Route::prefix('booking')->name('tagtoa.booking.dashboard.')->group(function () {
        Route::get('/', [BookingDashboard::class, 'index'])->name('index');
        Route::get('/create', [BookingDashboard::class, 'create'])->name('create');
        Route::post('/', [BookingDashboard::class, 'store'])->name('store');
        Route::get('/{id}/edit', [BookingDashboard::class, 'edit'])->name('edit');
        Route::put('/{id}', [BookingDashboard::class, 'update'])->name('update');
        Route::delete('/{id}', [BookingDashboard::class, 'destroy'])->name('destroy');
        Route::get('/{id}/bookings', [BookingDashboard::class, 'bookings'])->name('bookings');
        Route::post('/bookings/{booking}/status', [BookingDashboard::class, 'setStatus'])->name('bookings.status');
    });

    // REVIEWS (avis clients — modération)
    Route::prefix('reviews')->name('tagtoa.reviews.')->group(function () {
        Route::get('/', [\Modules\Tagtoa\App\Http\Controllers\Review\DashboardController::class, 'index'])->name('index');
        Route::post('/{id}/status', [\Modules\Tagtoa\App\Http\Controllers\Review\DashboardController::class, 'setStatus'])->name('status');
        Route::post('/{id}/reply', [\Modules\Tagtoa\App\Http\Controllers\Review\DashboardController::class, 'reply'])->name('reply');
        Route::post('/{id}/feature', [\Modules\Tagtoa\App\Http\Controllers\Review\DashboardController::class, 'feature'])->name('feature');
        Route::delete('/{id}', [\Modules\Tagtoa\App\Http\Controllers\Review\DashboardController::class, 'destroy'])->name('destroy');
    });

    // AUDIT (journal — lecture seule)
    Route::get('/audit', [\Modules\Tagtoa\App\Http\Controllers\Audit\DashboardController::class, 'index'])->name('tagtoa.audit.index');

    // ANALYTICS & CRM
    Route::get('/analytics', [\Modules\Tagtoa\App\Http\Controllers\Billing\AnalyticsController::class, 'index'])->name('tagtoa.analytics.index');
    Route::get('/customers', [\Modules\Tagtoa\App\Http\Controllers\Crm\CrmController::class, 'index'])->name('tagtoa.crm.index');

    // QR & PARTAGE
    Route::get('/qr', [\Modules\Tagtoa\App\Http\Controllers\Qr\QrController::class, 'index'])->name('tagtoa.qr.index');
    Route::get('/qr/poster/{type}/{id}', [\Modules\Tagtoa\App\Http\Controllers\Qr\QrController::class, 'poster'])->name('tagtoa.qr.poster');

    // PLAN / ABONNEMENT
    Route::get('/plan', [\Modules\Tagtoa\App\Http\Controllers\Billing\PlanController::class, 'index'])->name('tagtoa.plan.index');
    Route::post('/plan/subscribe', [\Modules\Tagtoa\App\Http\Controllers\Billing\PlanController::class, 'subscribe'])->name('tagtoa.plan.subscribe');

    // BILLING
    Route::get('/billing', [BillingController::class, 'index'])->name('tagtoa.billing.index');
    Route::put('/billing', [BillingController::class, 'update'])->name('tagtoa.billing.update');
    Route::post('/billing/settle', [BillingController::class, 'settle'])->name('tagtoa.billing.settle');
    Route::get('/billing/export', [BillingController::class, 'export'])->name('tagtoa.billing.export');
});
