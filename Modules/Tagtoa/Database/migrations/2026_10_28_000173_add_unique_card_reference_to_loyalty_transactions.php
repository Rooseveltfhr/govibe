<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA LOYALTY — une référence de paiement ne doit jamais créditer (ou
 * débiter) deux fois la même carte.
 *
 * `Card::lockForUpdate()` dans LoyaltyCardService empêche déjà une PERTE
 * d'écriture entre deux appels concurrents (le solde ne peut pas revenir en
 * arrière) — mais rien n'empêchait un DEUXIÈME appel, pour la même
 * référence, de créditer une deuxième fois une fois le premier verrou
 * relâché. Cas réel : une commande MENU payée confirmée à la fois par le
 * retour navigateur (`/pay/{gateway}/return`, public, sans throttle) et par
 * le webhook de la passerelle (`/pay/{gateway}/webhook`) — les deux
 * atterrissent sur MenuOrderService::markPaid() → earnPoints() avec la même
 * Order::reference.
 *
 * Scopée à (card_id, reference) plutôt que globale : MySQL autorise
 * plusieurs NULL dans un index unique, donc les mouvements sans référence
 * (recharge/débit manuels au tableau de bord) ne sont jamais concernés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->unique(['card_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->dropUnique(['card_id', 'reference']);
        });
    }
};
