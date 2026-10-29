<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA API — un `external_id` (référence de commande du site tiers) ne
 * doit jamais produire deux paiements.
 *
 * ApiPaymentService::create() lit « existe-t-il déjà un paiement pour cet
 * external_id ? » puis insère si non — sans contrainte en base, deux appels
 * arrivés au même instant (retry réseau côté intégrateur) peuvent tous les
 * deux lire « rien encore » avant que l'un des deux n'écrive, et produire
 * deux paiements — donc deux pages hébergées, deux tentatives d'encaissement
 * — pour une seule commande chez le site tiers.
 *
 * Scopée à (tenant_id, external_id), jamais globale : deux marchands
 * différents peuvent légitimement réutiliser la même référence de commande.
 * MySQL autorise plusieurs NULL dans un index unique, donc les paiements
 * sans external_id (champ optionnel) ne sont jamais concernés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_api_payments', function (Blueprint $table) {
            $table->unique(['tenant_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_api_payments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'external_id']);
        });
    }
};
