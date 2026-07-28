<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — suivi de stock optionnel sur les produits du menu.
 * Colonne nullable (null = stock non suivi / illimité), conforme à la règle
 * "nouvelles colonnes nullable/default uniquement".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_menu_items', 'stock')) {
            Schema::table('tagtoa_menu_items', function (Blueprint $table) {
                $table->integer('stock')->nullable()->after('is_available');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_menu_items', 'stock')) {
            Schema::table('tagtoa_menu_items', function (Blueprint $table) {
                $table->dropColumn('stock');
            });
        }
    }
};
