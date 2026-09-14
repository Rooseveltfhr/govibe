<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA Pay — lien de paiement « pro » + bibliothèque de moyens de paiement.
 *
 * `type`       : invoice (facturer un client) | donation (recevoir un don).
 * `is_library` : page TECHNIQUE, une par marchand, qui porte ses moyens de
 *                paiement configurés UNE SEULE FOIS. Elle n'apparaît jamais
 *                dans la liste des liens et n'est jamais accessible au public.
 *
 * Pourquoi une page technique plutôt que de rendre `payment_page_id` nullable
 * sur les méthodes : cette colonne porte une clé étrangère, et les preuves de
 * paiement sont supprimées en cascade depuis les méthodes. Un ALTER dessus sur
 * MySQL ne serait pas vérifiable depuis l'environnement de test (SQLite). On
 * reste donc strictement ADDITIF : aucune colonne existante n'est modifiée,
 * aucune ligne n'est supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_payment_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_payment_pages', 'type')) {
                $table->string('type', 16)->default('invoice')->after('title');
            }
            if (! Schema::hasColumn('tagtoa_payment_pages', 'is_library')) {
                $table->boolean('is_library')->default(false)->index()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_payment_pages', function (Blueprint $table) {
            foreach (['type', 'is_library'] as $col) {
                if (Schema::hasColumn('tagtoa_payment_pages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
