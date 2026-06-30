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
    | Forfaits d'abonnement (plan gating)
    |--------------------------------------------------------------------------
    | limits : nb max par module pour un tenant (null = illimité, 0 = bloqué).
    | features : libellés affichés (vitrine). Le forfait du marchand est stocké
    | dans tagtoa_subscriptions ; à défaut, 'default_plan'.
    */
    'default_plan' => env('TAGTOA_DEFAULT_PLAN', 'free'),

    'plans' => [
        'free' => [
            'label'  => 'Gratuit',
            'price'  => 0,
            'limits' => ['site' => 1, 'menu' => 1, 'pay' => 1, 'links' => 1, 'loyalty' => 0, 'event' => 0, 'pos' => 0, 'booking' => 0],
        ],
        'pro' => [
            'label'  => 'Pro',
            'price'  => 1500,
            'limits' => ['site' => null, 'menu' => null, 'pay' => null, 'links' => null, 'loyalty' => null, 'event' => null, 'pos' => null, 'booking' => null],
        ],
        'enterprise' => [
            'label'  => 'Enterprise',
            'price'  => null, // sur devis
            'limits' => ['site' => null, 'menu' => null, 'pay' => null, 'links' => null, 'loyalty' => null, 'event' => null, 'pos' => null, 'booking' => null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications (e-mail)
    |--------------------------------------------------------------------------
    | Opt-in : l'envoi réel n'a lieu que si `enabled` est vrai ET que la config
    | mail Laravel (SMTP) est en place côté hôte. Sinon, no-op silencieux.
    | Activer : TAGTOA_NOTIFY=true + MAIL_* configurés sur le VPS.
    */
    'notifications' => [
        'enabled' => env('TAGTOA_NOTIFY', false),
    ],

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
    | Passerelles de paiement API (auto)
    |--------------------------------------------------------------------------
    | Activées seulement si les identifiants sont présents (GatewayManager).
    | Définir les secrets via .env / GitHub secrets — JAMAIS en clair dans le repo.
    */
    'gateways' => [
        'moncash' => [
            'label'       => 'MonCash',
            'mode'        => env('TAGTOA_MONCASH_MODE', 'sandbox'),
            'credentials' => [
                'client_id' => env('TAGTOA_MONCASH_CLIENT_ID'),
                'secret'    => env('TAGTOA_MONCASH_SECRET'),
            ],
        ],
        'paypal' => [
            'label'       => 'PayPal',
            'mode'        => env('TAGTOA_PAYPAL_MODE', 'sandbox'),
            'credentials' => [
                'client_id' => env('TAGTOA_PAYPAL_CLIENT_ID'),
                'secret'    => env('TAGTOA_PAYPAL_SECRET'),
            ],
        ],
        'stripe' => [
            'label'       => 'Stripe',
            'credentials' => [
                'key'    => env('TAGTOA_STRIPE_KEY'),
                'secret' => env('TAGTOA_STRIPE_SECRET'),
            ],
            'webhook_secret' => env('TAGTOA_STRIPE_WEBHOOK_SECRET'),
        ],
        'coinpayments' => [
            'label'       => 'CoinPayments',
            'credentials' => [
                'merchant_id' => env('TAGTOA_COINPAYMENTS_MERCHANT_ID'),
                'public_key'  => env('TAGTOA_COINPAYMENTS_PUBLIC_KEY'),
                'private_key' => env('TAGTOA_COINPAYMENTS_PRIVATE_KEY'),
            ],
            'ipn_secret' => env('TAGTOA_COINPAYMENTS_IPN_SECRET'),
        ],
        'authorizenet' => [
            'label'       => 'Authorize.Net',
            'mode'        => env('TAGTOA_AUTHNET_MODE', 'sandbox'),
            'credentials' => [
                'login_id'        => env('TAGTOA_AUTHNET_LOGIN_ID'),
                'transaction_key' => env('TAGTOA_AUTHNET_TRANSACTION_KEY'),
            ],
        ],
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
