<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — la caisse peut vendre ce qui est au menu.
 *
 * Le marchand saisit un plat une fois dans son menu digital, et le vend aussi
 * au comptoir. Encore faut-il savoir DE QUEL catalogue vient chaque ligne :
 * les articles du menu et les boutons de la caisse ont chacun leurs
 * identifiants, qui commencent tous les deux à 1. Le plat n°7 et le bouton n°7
 * sont deux choses différentes.
 *
 * Sans cette colonne, retirer du stock pour « l'article 7 » viderait au hasard
 * l'une ou l'autre liste.
 *
 * Défaut « pos » : toutes les ventes déjà encaissées venaient de la caisse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_pos_sale_items', 'source')) {
                $table->string('source', 10)->default('pos')->after('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_pos_sale_items', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
