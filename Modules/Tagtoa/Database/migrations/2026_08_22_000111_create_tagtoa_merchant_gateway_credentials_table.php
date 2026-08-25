<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA Pay — identifiants API PROPRES AU MARCHAND.
 *
 * Complète le mode « le marchand encaisse » : jusqu'ici ce mode existait comme
 * réglage côté fondateur, mais le marchand n'avait nulle part où brancher son
 * compte MonCash/PayPal/Stripe — le mode était donc décoratif.
 *
 * Quand ces identifiants sont utilisés, l'argent va DIRECTEMENT chez le
 * marchand : TAGTOA ne touche jamais les fonds et n'a aucun rôle d'agrégateur.
 *
 * Mêmes garanties que la table plateforme : `values` est chiffré (APP_KEY, hors
 * base), et une clé est indexée par (tenant, driver) car plusieurs méthodes
 * partagent un même driver (usdt/usdc/btc/eth → coinpayments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_merchant_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('driver', 32);
            $table->text('values')->nullable(); // chiffré
            $table->timestamps();

            $table->unique(['tenant_id', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_merchant_gateway_credentials');
    }
};
