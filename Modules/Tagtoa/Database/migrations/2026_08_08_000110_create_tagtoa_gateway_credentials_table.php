<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA Pay — identifiants API des passerelles, saisis depuis le super-admin.
 *
 * Jusqu'ici les identifiants ne pouvaient venir QUE du .env du serveur : le
 * fondateur devait se connecter en SSH pour brancher MonCash ou PayPal. Cette
 * table permet de les saisir depuis l'interface.
 *
 * Sécurité :
 *  - une seule colonne `values`, chiffrée par Laravel (cast `encrypted:array`,
 *    clé = APP_KEY qui vit dans le .env, PAS en base) : un dump SQL volé ne
 *    révèle donc aucun secret ;
 *  - la clé est indexée par DRIVER (moncash, paypal, stripe, coinpayments) et
 *    non par type de méthode, car plusieurs types partagent un driver
 *    (usdt/usdc/btc/eth → coinpayments) ;
 *  - les valeurs ne sont jamais renvoyées au navigateur (voir GatewayController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 32)->unique();
            $table->text('values')->nullable(); // chiffré : credentials + mode + secrets webhook
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_gateway_credentials');
    }
};
