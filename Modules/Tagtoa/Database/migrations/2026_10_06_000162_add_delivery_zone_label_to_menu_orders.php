<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le nom de la zone choisie, figé sur la commande — comme le prix d'un
 * article : une zone renommée ou supprimée plus tard ne doit jamais changer
 * ce qu'affiche une commande déjà passée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->string('delivery_zone_label')->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_zone_label');
        });
    }
};
