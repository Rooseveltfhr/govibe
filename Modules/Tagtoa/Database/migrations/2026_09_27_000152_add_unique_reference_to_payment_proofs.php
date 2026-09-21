<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA PAY — une référence de transaction ne doit jamais produire deux
 * preuves de paiement.
 *
 * La garde « notifier une seule fois même si un webhook rejoue » (voir
 * CheckoutService::applyPayPagePaid()) suppose que PaymentProof::
 * firstOrCreate(['reference' => …, 'payment_page_id' => …], …) trouve
 * toujours la ligne déjà écrite plutôt que d'en créer une seconde. Sans
 * contrainte en base, deux webhooks arrivés au même instant peuvent tous les
 * deux lire « rien encore » avant que l'un des deux n'écrive — et produire
 * deux preuves, donc deux notifications, pour un seul paiement.
 *
 * `reference` vient de PayTransaction::generateReference() (déjà unique en
 * pratique), donc cette contrainte ne devrait jamais entrer en conflit avec
 * des données existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_payment_proofs', function (Blueprint $table) {
            $table->unique(['payment_page_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_payment_proofs', function (Blueprint $table) {
            $table->dropUnique(['payment_page_id', 'reference']);
        });
    }
};
