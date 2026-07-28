<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TAGTOA — API routes (préfixe /api, middleware api)
|--------------------------------------------------------------------------
| Réservé pour les endpoints scanner/POS offline-sync si on les passe en API.
*/

Route::middleware([])->prefix('v1/tagtoa')->name('api.tagtoa.')->group(function () {
    // endpoints à venir
});
