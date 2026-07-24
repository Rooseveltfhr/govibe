<?php

/*
 |------------------------------------------------------------------
 | FINPO 2026 — configuration de l'événement
 |------------------------------------------------------------------
 | Toutes les informations « statiques » de l'édition courante.
 | Modifiable sans toucher au code des vues.
 */

return [

    'name'      => 'FINPO 2026',
    'full_name' => 'Forum & Expo National des Institutions Publiques, Privées et Organisations',
    'subtitle'  => 'Forum National des Institutions Publiques, Privées et Organisations',
    'tagline'   => "Connecter les institutions. Construire des partenariats. Accélérer le développement d'Haïti.",
    'edition'   => 2026,

    // Mots animés du hero
    'hero_words' => ['Forum', 'Expo', 'Networking', 'Tech', 'Innovation'],

    'organizer' => [
        'name'    => 'GOVIBE Innovation Hub',
        'url'     => 'https://govibe.ht',
        'tagline' => "Hub d'innovation et d'entrepreneuriat au service du développement d'Haïti.",
    ],

    // Dates de l'événement
    'starts_at' => env('FINPO_STARTS_AT', '2026-11-18 08:00:00'),
    'ends_at'   => env('FINPO_ENDS_AT', '2026-11-20 18:00:00'),
    'timezone'  => 'America/Port-au-Prince',

    'venue' => [
        'name'    => 'Centre de Convention et de Documentation de la BRH',
        'city'    => 'Port-au-Prince',
        'country' => 'Haïti',
        'address' => 'Boulevard Toussaint Louverture, Port-au-Prince, Haïti',
        'map_q'   => 'Banque de la République d\'Haïti, Port-au-Prince',
    ],

    'contact' => [
        'email'    => 'info@finpo.ht',
        'phone'    => '+509 4444-2026',
        'whatsapp' => '+50944442026',
    ],

    'social' => [
        'facebook'  => 'https://facebook.com/finpohaiti',
        'instagram' => 'https://instagram.com/finpohaiti',
        'linkedin'  => 'https://linkedin.com/company/finpohaiti',
        'x'         => 'https://x.com/finpohaiti',
        'youtube'   => 'https://youtube.com/@finpohaiti',
    ],

    'previous_edition_video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'brochure_url'           => '/downloads/FINPO-2026-Brochure.pdf',

    // Devises supportées (affichage)
    'currency' => 'HTG',

    // Niveaux de sponsoring (ordre d'affichage)
    'sponsor_levels' => [
        'title'     => ['label' => 'Title Sponsor',     'color' => '#e8b931', 'price' => 2500000],
        'diamond'   => ['label' => 'Diamant',           'color' => '#7de2ff', 'price' => 1500000],
        'platinum'  => ['label' => 'Platine',           'color' => '#cfd8e3', 'price' => 1000000],
        'gold'      => ['label' => 'Or',                'color' => '#f2c14e', 'price' => 600000],
        'silver'    => ['label' => 'Argent',            'color' => '#aab4c0', 'price' => 350000],
        'bronze'    => ['label' => 'Bronze',            'color' => '#c98a4b', 'price' => 200000],
        'community' => ['label' => 'Sponsor Communauté','color' => '#7ee0a3', 'price' => 75000],
    ],

    // Catégories de participants + couleur de badge
    'attendee_categories' => [
        'government'    => ['label' => 'Institution publique',   'color' => '#1d4ed8'],
        'private'       => ['label' => 'Entreprise privée',      'color' => '#0e7490'],
        'ngo'           => ['label' => 'ONG',                    'color' => '#15803d'],
        'association'   => ['label' => 'Association',            'color' => '#4d7c0f'],
        'university'    => ['label' => 'Université',             'color' => '#7c3aed'],
        'startup'       => ['label' => 'Startup',                'color' => '#db2777'],
        'professional'  => ['label' => 'Professionnel',          'color' => '#0f766e'],
        'student'       => ['label' => 'Étudiant',               'color' => '#ca8a04'],
        'media'         => ['label' => 'Média',                  'color' => '#ea580c'],
        'vip'           => ['label' => 'VIP',                    'color' => '#b45309'],
        'speaker'       => ['label' => 'Intervenant',            'color' => '#9333ea'],
        'sponsor'       => ['label' => 'Sponsor',                'color' => '#e8b931'],
        'partner'       => ['label' => 'Partenaire',             'color' => '#2563eb'],
        'exhibitor'     => ['label' => 'Exposant',               'color' => '#dc2626'],
        'volunteer'     => ['label' => 'Volontaire',             'color' => '#059669'],
        'staff'         => ['label' => 'Staff',                  'color' => '#334155'],
        'press'         => ['label' => 'Presse',                 'color' => '#c2410c'],
    ],

    // Catégories d'intervenants (filtres)
    'speaker_categories' => [
        'government'    => 'Gouvernement',
        'private'       => 'Secteur privé',
        'ngo'           => 'ONG',
        'international' => 'International',
        'technology'    => 'Technologie',
        'finance'       => 'Finance',
        'education'     => 'Éducation',
        'health'        => 'Santé',
        'agriculture'   => 'Agriculture',
        'environment'   => 'Environnement',
    ],

    // Catégories de partenaires
    'partner_categories' => [
        'government'    => 'Gouvernement',
        'international' => 'Organisations internationales',
        'ngo'           => 'ONG',
        'private'       => 'Secteur privé',
        'academic'      => 'Institutions académiques',
        'media'         => 'Médias',
        'strategic'     => 'Partenaires stratégiques',
    ],

    // Types de sessions du programme
    'session_types' => [
        'keynote'   => 'Keynote',
        'panel'     => 'Panel',
        'workshop'  => 'Atelier',
        'ceremony'  => 'Cérémonie',
        'networking'=> 'Networking',
        'expo'      => 'Expo',
        'awards'    => 'Awards',
    ],

    // Modes de paiement affichés à l'inscription
    'payment_methods' => [
        'moncash'  => 'MonCash',
        'natcash'  => 'NatCash',
        'card'     => 'Visa / MasterCard (Stripe)',
        'paypal'   => 'PayPal',
        'transfer' => 'Virement bancaire',
        'cash'     => 'Espèces (sur place)',
        'free'     => 'Inscription gratuite',
    ],
];
