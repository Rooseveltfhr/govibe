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
];
