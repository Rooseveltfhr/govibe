<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Langues supportées
    |--------------------------------------------------------------------------
    | fr = langue source. ht (créole haïtien), en, es.
    */

    'locales' => ['fr', 'ht', 'en', 'es'],

    'default_locale' => env('GOVIBE_DEFAULT_LOCALE', 'fr'),

    /*
    |--------------------------------------------------------------------------
    | Devise préférée par langue (affichage)
    |--------------------------------------------------------------------------
    */

    'currency_by_locale' => [
        'ht' => 'HTG',
        'fr' => 'USD',
        'en' => 'USD',
        'es' => 'DOP',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Router — poids par défaut du scoring (Phase 1)
    |--------------------------------------------------------------------------
    | Surchargés par plan, par organisation, puis par requête.
    */

    'router' => [
        'weights' => [
            'cost' => (float) env('GOVIBE_ROUTER_W_COST', 0.35),
            'speed' => (float) env('GOVIBE_ROUTER_W_SPEED', 0.20),
            'quality' => (float) env('GOVIBE_ROUTER_W_QUALITY', 0.25),
            'preference' => (float) env('GOVIBE_ROUTER_W_PREFERENCE', 0.10),
            'availability' => (float) env('GOVIBE_ROUTER_W_AVAILABILITY', 0.10),
        ],
    ],

];
