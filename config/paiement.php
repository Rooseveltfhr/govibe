<?php

use App\Paiement\Pilotes\MonCash;

return [

    /*
    | Pilotes disponibles. En ajouter un se fait ici et dans une classe qui
    | implémente PiloteApi : le module de paiement n'est pas retouché.
    */
    'pilotes' => [
        MonCash::class,
    ],

    /*
    | Adresses MonCash. Configurables pour absorber une évolution de leur côté
    | sans redéployer du code.
    */
    'moncash' => [
        'base_test' => env(
            'MONCASH_BASE_TEST',
            'https://sandbox.moncashbutton.digicelgroup.com'
        ),
        'base_production' => env(
            'MONCASH_BASE_PRODUCTION',
            'https://moncashbutton.digicelgroup.com'
        ),
    ],

    /*
    | Écart toléré entre le montant demandé et celui encaissé, en unités de la
    | devise. MonCash arrondit à la gourde : sans cette tolérance, un paiement
    | correct serait signalé comme suspect.
    */
    'tolerance_montant' => 1.0,
];
