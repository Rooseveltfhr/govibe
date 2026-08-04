<?php

use Illuminate\Support\Facades\Route;
use Modules\AIServices\Catalog\Http\ModelsController;
use Modules\AIServices\Chat\Http\ChatCompletionsController;

/*
 * API publique GOVIBE AI — version 1.
 *
 * L'authentification par clé d'API, les quotas et la limitation de débit
 * arrivent avec le module ApiPlatform (Phase 2). Les chemins, eux, sont déjà
 * ceux de la version définitive.
 */

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::post('chat/completions', ChatCompletionsController::class)->name('chat.completions');
    Route::get('models', ModelsController::class)->name('models');
});
