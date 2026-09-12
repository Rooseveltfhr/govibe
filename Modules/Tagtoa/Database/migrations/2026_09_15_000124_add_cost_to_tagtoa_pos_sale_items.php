<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — le coût du jour de la vente, figé sur la ligne.
 *
 * Sans cette colonne, le profit d'un mois passé se recalculerait avec le prix
 * d'achat d'AUJOURD'HUI. Le marchand change de fournisseur, l'inflation passe,
 * et le bénéfice de septembre se met à bouger tout seul en novembre.
 *
 * Un rapport dont les chiffres changent après coup ne sert à rien : la ligne
 * de vente fige donc le coût comme elle fige déjà le nom et le prix.
 *
 * Nullable, et qui le reste : les ventes déjà encaissées n'ont pas de coût
 * connu, et « 0 » leur prêterait une marge totale qu'elles n'avaient pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_pos_sale_items', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_pos_sale_items', 'cost_price')) {
                $table->dropColumn('cost_price');
            }
        });
    }
};
