<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Vidéo YouTube en autoplay sous le hero de la page d'accueil (brief client).
    // Réglable sans redéploiement via .env : juste l'identifiant vidéo
    // (ex. "dQw4w9WgXcQ"), pas l'URL complète. Valeur par défaut = le Short
    // fourni par le client (https://youtube.com/shorts/iE3z01GRFOw).
    'home_video' => [
        'id' => env('MSN_HOME_VIDEO_ID', 'iE3z01GRFOw'),
    ],

];
