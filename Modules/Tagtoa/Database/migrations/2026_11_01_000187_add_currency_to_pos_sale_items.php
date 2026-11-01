<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA DEVISES (CUR-2) — la ligne de vente de la caisse fige la devise d'origine, le prix
 * d'origine et le taux utilisé, comme les lignes de commande Menu (CUR-1).
 *
 * `price_check` dit comment le prix a été retenu pour une vente reprise HORS LIGNE :
 *   - NULL         : vente normale, taux du moment ;
 *   - 'till_rate'  : le prix de la caisse correspond à un taux valable dans les 24 h avant la vente ;
 *   - 'repriced'   : le prix de la caisse ne correspondait à aucun taux valable → taux du jour (À VÉRIFIER) ;
 *   - 'no_rate'    : plus de taux actif → prix encaissé gardé tel quel (À VÉRIFIER).
 *
 * Colonnes nullables, sans index. Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_pos_sale_items') && ! Schema::hasColumn('tagtoa_pos_sale_items', 'currency_original')) {
            Schema::table('tagtoa_pos_sale_items', function (Blueprint $table) {
                $table->string('currency_original', 8)->nullable();
                $table->decimal('unit_price_original', 14, 2)->nullable();
                $table->decimal('rate_used', 18, 6)->nullable();
                $table->string('price_check', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_pos_sale_items') && Schema::hasColumn('tagtoa_pos_sale_items', 'currency_original')) {
            Schema::table('tagtoa_pos_sale_items', function (Blueprint $table) {
                $table->dropColumn(['currency_original', 'unit_price_original', 'rate_used', 'price_check']);
            });
        }
    }
};
