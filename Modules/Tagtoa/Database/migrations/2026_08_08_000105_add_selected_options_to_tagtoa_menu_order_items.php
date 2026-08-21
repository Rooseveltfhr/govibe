<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — snapshot JSON des options choisies par le client au moment de
 * la commande (label + price_delta), cohérent avec le reste de la ligne
 * (name/price déjà figés) : jamais de référence live vers les choix.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_menu_order_items', 'selected_options')) {
            Schema::table('tagtoa_menu_order_items', function (Blueprint $table) {
                $table->json('selected_options')->nullable()->after('qty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_menu_order_items', 'selected_options')) {
            Schema::table('tagtoa_menu_order_items', function (Blueprint $table) {
                $table->dropColumn('selected_options');
            });
        }
    }
};
