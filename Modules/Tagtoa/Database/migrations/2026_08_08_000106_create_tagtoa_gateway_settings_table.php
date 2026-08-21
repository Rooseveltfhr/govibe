<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA Pay — réglages PLATEFORME par passerelle (super_admin).
 *
 * Surcharge le registre codé en dur (Support/PaymentGateway::GATEWAYS) de la
 * même façon que `tagtoa_plans` surcharge config('tagtoa.plans') : une ligne
 * n'existe que si le fondateur a modifié quelque chose — l'absence de ligne
 * signifie « valeurs par défaut du code ». Tolérant : si la table est absente
 * (migration pas encore passée), le catalogue retombe sur le registre.
 *
 * `credential_mode` est le point de sécurité important :
 *   platform = les identifiants API de TAGTOA encaissent (agrégateur — n'activer
 *              que si l'accord PayPal/Stripe/MonCash existe réellement) ;
 *   merchant = le marchand branche ses propres identifiants, l'argent va direct
 *              chez lui et TAGTOA ne touche jamais les fonds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32)->unique(); // clé du registre : moncash, paypal, card…
            $table->boolean('is_enabled')->default(true);
            $table->string('credential_mode', 16)->default('platform'); // platform | merchant
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->decimal('fee_fixed', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_gateway_settings');
    }
};
