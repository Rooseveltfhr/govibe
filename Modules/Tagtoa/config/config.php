<?php

return [
    'name' => 'Tagtoa',

    /*
    |--------------------------------------------------------------------------
    | Modèle de revenu par défaut (plateforme)
    |--------------------------------------------------------------------------
    | subscription | commission | both  (surchargé par marchand via Billing)
    */
    'revenue_model'      => env('TAGTOA_REVENUE_MODEL', 'subscription'),
    'commission_percent' => env('TAGTOA_COMMISSION_PERCENT', 0),
    'commission_fixed'   => env('TAGTOA_COMMISSION_FIXED', 0),
    'default_currency'   => env('TAGTOA_CURRENCY', 'HTG'),

    /*
    |--------------------------------------------------------------------------
    | Internationalisation (i18n)
    |--------------------------------------------------------------------------
    | Langues supportées + devise par défaut associée à chaque langue.
    | Le marchand/visiteur peut changer ; chaque page garde sa propre devise.
    */
    'default_locale' => env('TAGTOA_LOCALE', 'fr'),

    'locales' => [
        'fr' => ['label' => 'Français', 'flag' => '🇫🇷', 'currency' => 'EUR'],
        'ht' => ['label' => 'Kreyòl',   'flag' => '🇭🇹', 'currency' => 'HTG'],
        'en' => ['label' => 'English',  'flag' => '🇺🇸', 'currency' => 'USD'],
        'es' => ['label' => 'Español',  'flag' => '🇩🇴', 'currency' => 'DOP'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Devises supportées
    |--------------------------------------------------------------------------
    | symbol : symbole affiché · decimals : nb de décimales ·
    | position : before|after (symbole avant ou après le montant).
    */
    'currencies' => [
        'HTG' => ['symbol' => 'G',   'name' => 'Gourde haïtienne',  'decimals' => 0, 'position' => 'after'],
        'USD' => ['symbol' => '$',   'name' => 'US Dollar',         'decimals' => 2, 'position' => 'before'],
        'EUR' => ['symbol' => '€',   'name' => 'Euro',              'decimals' => 2, 'position' => 'after'],
        'DOP' => ['symbol' => 'RD$', 'name' => 'Peso dominicain',   'decimals' => 2, 'position' => 'before'],
        'CAD' => ['symbol' => 'C$',  'name' => 'Dollar canadien',   'decimals' => 2, 'position' => 'before'],
    ],
];
