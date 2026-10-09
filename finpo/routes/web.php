<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\Admin;

/*
|--------------------------------------------------------------------------
| FINPO 2026 — routes publiques
|--------------------------------------------------------------------------
*/

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/a-propos', [SiteController::class, 'about'])->name('about');
Route::get('/forum', [SiteController::class, 'forum'])->name('forum');
Route::get('/expo', [SiteController::class, 'expo'])->name('expo');
Route::get('/expo/{exhibitor:slug}', [SiteController::class, 'exhibitor'])->name('expo.show');
Route::get('/programme', [SiteController::class, 'programme'])->name('programme');
Route::get('/programme/ics/{session}', [SiteController::class, 'sessionIcs'])->name('programme.ics');
Route::get('/intervenants', [SiteController::class, 'speakers'])->name('speakers');
Route::get('/intervenants/{speaker:slug}', [SiteController::class, 'speaker'])->name('speakers.show');
Route::get('/partenaires', [SiteController::class, 'partners'])->name('partners');
Route::post('/partenaires/candidature', [SiteController::class, 'partnerApply'])
    ->middleware('throttle:10,1')->name('partners.apply');
Route::get('/sponsors', [SiteController::class, 'sponsors'])->name('sponsors');
Route::post('/sponsors/candidature', [SiteController::class, 'sponsorApply'])
    ->middleware('throttle:10,1')->name('sponsors.apply');
Route::get('/exposants', [SiteController::class, 'exhibitors'])->name('exhibitors');
Route::post('/exposants/reservation', [SiteController::class, 'exhibitorApply'])
    ->middleware('throttle:10,1')->name('exhibitors.apply');
Route::get('/networking', [SiteController::class, 'networking'])->name('networking');
Route::get('/awards', [SiteController::class, 'awards'])->name('awards');
Route::get('/media', [SiteController::class, 'media'])->name('media');
Route::get('/galerie', [SiteController::class, 'gallery'])->name('gallery');
Route::get('/actualites', [SiteController::class, 'news'])->name('news');
Route::get('/actualites/{post:slug}', [SiteController::class, 'newsShow'])->name('news.show');
Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
Route::post('/contact', [SiteController::class, 'contactSubmit'])
    ->middleware('throttle:6,1')->name('contact.submit');
Route::post('/newsletter', [SiteController::class, 'newsletter'])
    ->middleware('throttle:6,1')->name('newsletter');

/* Inscription & billets */
Route::get('/inscription', [RegistrationController::class, 'index'])->name('register');
Route::get('/inscription/{category:slug}', [RegistrationController::class, 'form'])->name('register.form');
Route::post('/inscription/{category:slug}', [RegistrationController::class, 'store'])
    ->middleware('throttle:12,1')->name('register.store');
Route::post('/inscription-coupon', [RegistrationController::class, 'couponCheck'])
    ->middleware('throttle:20,1')->name('register.coupon');
Route::get('/billet/{token}', [RegistrationController::class, 'ticket'])->name('ticket.show');
Route::get('/billet/{token}/imprimer', [RegistrationController::class, 'ticketPrint'])->name('ticket.print');
Route::get('/billet/{token}/ics', [RegistrationController::class, 'ticketIcs'])->name('ticket.ics');
Route::get('/badge/{token}', [RegistrationController::class, 'badge'])->name('badge.show');

/* Certificats */
Route::get('/certificat/{number}', [CertificateController::class, 'show'])->name('certificate.show');
Route::get('/verification/{number?}', [CertificateController::class, 'verify'])->name('certificate.verify');

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', [Admin\AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [Admin\AuthController::class, 'login'])
    ->middleware('throttle:8,1')->name('admin.login.post');
Route::post('/admin/logout', [Admin\AuthController::class, 'logout'])->name('admin.logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Billetterie — l'admin gère les catégories de billets (prix, quotas…)
    Route::resource('tickets', Admin\TicketCategoryController::class)->except(['show']);
    Route::resource('coupons', Admin\CouponController::class)->except(['show']);

    // Participants / inscriptions
    Route::get('registrations', [Admin\RegistrationAdminController::class, 'index'])->name('registrations.index');
    Route::get('registrations/export', [Admin\RegistrationAdminController::class, 'export'])->name('registrations.export');
    Route::get('registrations/{registration}', [Admin\RegistrationAdminController::class, 'show'])->name('registrations.show');
    Route::post('registrations/{registration}/status', [Admin\RegistrationAdminController::class, 'status'])->name('registrations.status');
    Route::post('registrations/{registration}/certificate', [Admin\RegistrationAdminController::class, 'certificate'])->name('registrations.certificate');

    // Check-in
    Route::get('checkin', [Admin\CheckinController::class, 'index'])->name('checkin');
    Route::post('checkin/scan', [Admin\CheckinController::class, 'scan'])->name('checkin.scan');
    Route::get('checkin/search', [Admin\CheckinController::class, 'search'])->name('checkin.search');

    // Contenu événement
    Route::resource('speakers', Admin\SpeakerAdminController::class)->except(['show']);
    Route::resource('sessions', Admin\SessionAdminController::class)->except(['show']);
    Route::resource('partners', Admin\PartnerAdminController::class)->except(['show']);
    Route::resource('sponsors', Admin\SponsorAdminController::class)->except(['show']);
    Route::resource('exhibitors', Admin\ExhibitorAdminController::class)->except(['show']);
    Route::resource('booths', Admin\BoothAdminController::class)->except(['show']);
    Route::resource('news', Admin\NewsAdminController::class)->except(['show']);
    Route::resource('gallery', Admin\GalleryAdminController::class)->except(['show']);

    // Messages + audit
    Route::get('messages', [Admin\MessageAdminController::class, 'index'])->name('messages.index');
    Route::delete('messages/{message}', [Admin\MessageAdminController::class, 'destroy'])->name('messages.destroy');
});
