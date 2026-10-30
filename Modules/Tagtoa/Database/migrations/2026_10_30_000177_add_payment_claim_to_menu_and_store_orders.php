<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'ai payé » : le client qui règle par un moyen MANUEL du marchand (envoi
 * MonCash/NatCash, virement…) le déclare sur la page de paiement. La
 * déclaration ne paie RIEN : elle est seulement montrée au marchand, qui
 * vérifie sur son téléphone puis confirme (« Encaisser »).
 *
 * Deux colonnes nullables par table — aucun index, aucune contrainte, rien qui
 * puisse faire échouer `migrate` en production.
 */
return new class extends Migration
{
    private const TABLES = ['tagtoa_menu_orders', 'tagtoa_store_orders'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('payment_claim')->nullable();
                $table->timestamp('payment_claimed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['payment_claim', 'payment_claimed_at']);
            });
        }
    }
};
