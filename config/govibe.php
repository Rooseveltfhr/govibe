<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domaine de l'application
    |--------------------------------------------------------------------------
    |
    | Domaine qui sert l'ERP et le portail client, séparé du site vitrine.
    |
    | Tant que la valeur est vide, l'ERP et le portail répondent sur n'importe
    | quel domaine : c'est le comportement actuel, et rien ne casse. Renseigner
    | GOVIBE_DOMAINE_APP n'est à faire QU'UNE FOIS que le sous-domaine résout,
    | possède son vhost et son certificat — sinon l'isolation enfermerait
    | l'équipe dehors de son propre ERP.
    |
    */
    'domaine_app' => env('GOVIBE_DOMAINE_APP'),

    /*
    | Domaine du site vitrine, utilisé pour renvoyer un visiteur qui arrive sur
    | le portail par erreur.
    */
    'domaine_vitrine' => env('GOVIBE_DOMAINE_VITRINE', 'govibeht.com'),

    /*
    |--------------------------------------------------------------------------
    | Devises et taux de change
    |--------------------------------------------------------------------------
    |
    | Les montants sont stockés dans leur devise d'origine ; le taux appliqué
    | est celui réglé par le super admin, gelé au moment de l'engagement. Un
    | taux qui bouge ne doit jamais réécrire un montant déjà annoncé.
    |
    */
    'devises' => ['USD', 'HTG'],
    'devise_par_defaut' => env('GOVIBE_DEVISE', 'HTG'),

    /*
    |--------------------------------------------------------------------------
    | Portail client
    |--------------------------------------------------------------------------
    */
    'portail' => [
        // Un compte n'accède à son historique qu'une fois l'adresse vérifiée :
        // sans cela, s'inscrire avec l'email d'un tiers suffirait à lire ses
        // factures.
        'verification_email_obligatoire' => env('GOVIBE_PORTAIL_VERIF_EMAIL', true),

        // Tentatives de connexion par minute, par couple email + IP.
        'tentatives_connexion' => 5,
    ],
];
