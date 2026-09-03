<?php

use Illuminate\Support\Facades\Route;
use Modules\Tagtoa\App\Http\Controllers\Api\PaymentApiController;
use Modules\Tagtoa\App\Http\Middleware\AuthenticateApiKey;

/*
|--------------------------------------------------------------------------
| TAGTOA — API développeur (préfixe /api/v1/tagtoa)
|--------------------------------------------------------------------------
| Permet à n'importe quel site tiers d'encaisser via les méthodes de paiement
| TAGTOA du marchand. Authentification par clé API (jamais de session/CSRF) —
| voir Middleware/AuthenticateApiKey et la page /tagtoa/developer.
|
| Le débit est borné par clé (throttle) : une intégration qui boucle ne peut
| pas saturer le serveur pour les autres marchands.
*/

Route::prefix('v1/tagtoa')
    ->middleware([AuthenticateApiKey::class, 'throttle:120,1'])
    ->name('api.tagtoa.')
    ->group(function () {
        Route::get('/ping', [PaymentApiController::class, 'ping'])->name('ping');
        Route::get('/payment-methods', [PaymentApiController::class, 'methods'])->name('methods');
        Route::post('/payments', [PaymentApiController::class, 'store'])->name('payments.store');
        Route::get('/payments/{reference}', [PaymentApiController::class, 'show'])->name('payments.show');
    });
