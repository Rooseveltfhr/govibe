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

    /*
    |--------------------------------------------------------------------------
    | Sécurité NFC — NTAG424 DNA (SUN/SDM, anti-clone/anti-rejeu)
    |--------------------------------------------------------------------------
    | DORMANT tant que la clé n'est pas fournie. Pour les cartes PREMIUM
    | (paiement/event) : chaque tap produit un CMAC signé + un compteur → clone
    | impossible. Activer : fournir TAGTOA_NTAG424_KEY (clé maître AES-128 hex)
    | et valider contre un vrai tag AVANT de câbler dans l'encaissement.
    */
    'nfc' => [
        'ntag424' => [
            'enabled' => env('TAGTOA_NTAG424_ENABLE', false),
            'key'     => env('TAGTOA_NTAG424_KEY'), // clé maître AES-128 (32 hex), jamais en clair ici
        ],
    ],

    'plans' => [
        // `cards` = émission/activation de cartes NFC TAGTOA (closed-loop). Réservé
        // aux forfaits payants supérieurs (Enterprise/Revendeur/Franchise).
        'free' => [
            'label'  => 'Gratuit',
            'price'  => 0,
            'limits' => ['site' => 1, 'menu' => 1, 'pay' => 1, 'links' => 1, 'loyalty' => 0, 'event' => 0, 'pos' => 0, 'booking' => 0, 'staff' => 0, 'store' => 1, 'cards' => 0],
        ],
        'pro' => [
            'label'  => 'Pro',
            'price'  => 1500,
            // `staff` = nombre max de comptes staff PAR ÉVÉNEMENT (terrain, PIN).
            'limits' => ['site' => null, 'menu' => null, 'pay' => null, 'links' => null, 'loyalty' => null, 'event' => null, 'pos' => null, 'booking' => null, 'staff' => 10, 'store' => null, 'cards' => 0],
        ],
        'enterprise' => [
            'label'  => 'Enterprise',
            'price'  => null, // sur devis
            'limits' => ['site' => null, 'menu' => null, 'pay' => null, 'links' => null, 'loyalty' => null, 'event' => null, 'pos' => null, 'booking' => null, 'staff' => null, 'store' => null, 'cards' => null],
        ],
        // Revendeur : active/émet des cartes NFC, revend localement (marge matériel).
        'reseller' => [
            'label'  => 'Revendeur',
            'price'  => 5000,
            'limits' => ['site' => null, 'menu' => null, 'pay' => null, 'links' => null, 'loyalty' => null, 'event' => null, 'pos' => null, 'booking' => null, 'staff' => null, 'store' => null, 'cards' => null],
        ],
        // Franchise : déploiement pays/marque, tout illimité (sur devis).
        'franchise' => [
            'label'  => 'Franchise',
            'price'  => null, // sur devis
            'limits' => ['site' => null, 'menu' => null, 'pay' => null, 'links' => null, 'loyalty' => null, 'event' => null, 'pos' => null, 'booking' => null, 'staff' => null, 'store' => null, 'cards' => null],
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
        // WhatsApp via Twilio (opt-in) — no-op tant que les identifiants ne sont
        // pas définis sur le VPS. Ne JAMAIS mettre les secrets en clair ici.
        'whatsapp' => [
            'enabled' => env('TAGTOA_WA_NOTIFY', false),
            'sid'     => env('TAGTOA_TWILIO_SID'),
            'token'   => env('TAGTOA_TWILIO_TOKEN'),
            'from'    => env('TAGTOA_TWILIO_WHATSAPP_FROM'), // ex. +14155238886
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact commercial (public) — pour les solutions « sur demande »
    |--------------------------------------------------------------------------
    | Numéro WhatsApp (chiffres seulement, ex. 50937123456) + e-mail affichés
    | sur la vitrine (Identity/Access). À définir sur le VPS via .env.
    */
    'contact' => [
        'whatsapp' => env('TAGTOA_CONTACT_WHATSAPP', ''),
        'email'    => env('TAGTOA_CONTACT_EMAIL', ''),
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
    | Modules visibles dans le tableau de bord marchand
    |--------------------------------------------------------------------------
    | Ce que le marchand voit dans sa barre latérale et sur sa page d'accueil.
    | Masquer n'est PAS supprimer : les routes d'un module absent d'ici restent
    | servies et ses données intactes — un marchand qui a déjà des liens NFC
    | imprimés ou des cartes de fidélité ne perd rien.
    |
    | Rallumer un module = ajouter sa clé ici. Clés disponibles :
    |   menu, pos, event, pay, analytics, customers, reviews, qr, plan,
    |   site, store, cards, loyalty, links, booking, billing, audit
    */
    'modules_enabled' => \Modules\Tagtoa\App\Support\DashboardModules::DEFAULT_ENABLED,

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
        // International (plateforme mondiale)
        'GBP' => ['symbol' => '£',    'name' => 'Livre sterling',        'decimals' => 2, 'position' => 'before'],
        'MXN' => ['symbol' => 'MX$',  'name' => 'Peso mexicain',         'decimals' => 2, 'position' => 'before'],
        'BRL' => ['symbol' => 'R$',   'name' => 'Real brésilien',        'decimals' => 2, 'position' => 'before'],
        'XOF' => ['symbol' => 'CFA',  'name' => 'Franc CFA (UEMOA)',     'decimals' => 0, 'position' => 'after'],
        'XAF' => ['symbol' => 'FCFA', 'name' => 'Franc CFA (CEMAC)',     'decimals' => 0, 'position' => 'after'],
        'NGN' => ['symbol' => '₦',    'name' => 'Naira nigérian',        'decimals' => 2, 'position' => 'before'],
        'GHS' => ['symbol' => 'GH₵',  'name' => 'Cedi ghanéen',          'decimals' => 2, 'position' => 'before'],
        'KES' => ['symbol' => 'KSh',  'name' => 'Shilling kényan',       'decimals' => 2, 'position' => 'before'],
        'ZAR' => ['symbol' => 'R',    'name' => 'Rand sud-africain',     'decimals' => 2, 'position' => 'before'],
        'COP' => ['symbol' => 'CO$',  'name' => 'Peso colombien',        'decimals' => 2, 'position' => 'before'],
    ],
];
