<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMMENT une commande de menu a été payée, et par QUI.
 *
 * Jusqu'ici « Encaisser » ne gardait que `payment_status = paid` : ni le
 * moyen (espèces ? MonCash ? carte ?), ni la personne, ni l'heure. Le
 * marchand ne pouvait pas rapprocher sa caisse, et une commande « payée »
 * par la passerelle ne se distinguait pas d'une commande encaissée à la main.
 *
 * Trois colonnes nullables : les commandes déjà payées restent valides, avec
 * « moyen inconnu ». Aucun index, aucune contrainte — rien qui puisse faire
 * échouer `migrate` en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->string('paid_by', 120)->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paid_at', 'paid_by']);
        });
    }
};
