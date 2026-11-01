<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA DEVISES (CUR-3) — devise par produit de la boutique en ligne, comme pour les articles Menu (CUR-1).
 *
 * - `tagtoa_store_products.currency` : NULL = devise de la boutique (rien ne change pour l'existant).
 * - `tagtoa_store_order_items` : la ligne fige le prix d'origine, sa devise et le taux utilisé.
 *
 * Colonnes nullables, sans index. Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_store_products') && ! Schema::hasColumn('tagtoa_store_products', 'currency')) {
            Schema::table('tagtoa_store_products', function (Blueprint $table) {
                $table->string('currency', 8)->nullable();
            });
        }
        if (Schema::hasTable('tagtoa_store_order_items') && ! Schema::hasColumn('tagtoa_store_order_items', 'currency_original')) {
            Schema::table('tagtoa_store_order_items', function (Blueprint $table) {
                $table->string('currency_original', 8)->nullable();
                $table->decimal('unit_price_original', 14, 2)->nullable();
                $table->decimal('rate_used', 18, 6)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_store_order_items') && Schema::hasColumn('tagtoa_store_order_items', 'currency_original')) {
            Schema::table('tagtoa_store_order_items', function (Blueprint $table) {
                $table->dropColumn(['currency_original', 'unit_price_original', 'rate_used']);
            });
        }
        if (Schema::hasTable('tagtoa_store_products') && Schema::hasColumn('tagtoa_store_products', 'currency')) {
            Schema::table('tagtoa_store_products', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
