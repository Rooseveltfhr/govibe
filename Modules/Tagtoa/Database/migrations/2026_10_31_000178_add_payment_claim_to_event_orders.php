<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'ai payé » pour les commandes de BILLETTERIE : même mécanisme que pour le
 * menu et la boutique (migration 000177). Le client qui règle par un moyen
 * MANUEL du marchand le déclare sur la page de paiement ; la déclaration ne paie
 * RIEN, elle est montrée à l'organisateur qui vérifie puis « Encaisse ».
 *
 * Deux colonnes nullables, sans index ni contrainte : rien qui puisse faire
 * échouer `migrate`. Gardées par hasColumn : un redéploiement après un échec
 * partiel ne doit pas retomber sur « column already exists ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_orders', 'payment_claim')) {
            Schema::table('tagtoa_ev_orders', function (Blueprint $table) {
                $table->text('payment_claim')->nullable();
            });
        }
        if (! Schema::hasColumn('tagtoa_ev_orders', 'payment_claimed_at')) {
            Schema::table('tagtoa_ev_orders', function (Blueprint $table) {
                $table->timestamp('payment_claimed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_claim', 'payment_claimed_at'] as $col) {
            if (Schema::hasColumn('tagtoa_ev_orders', $col)) {
                Schema::table('tagtoa_ev_orders', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
