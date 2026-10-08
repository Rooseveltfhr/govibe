<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — le pays d'une zone de livraison, demandé AVANT la ville/
 * commune (voir le champ `name`, qui reste la ville/commune en texte libre —
 * aucune base de données administrative Haïti/RD/US/CA complète n'existe
 * dans le projet, un remplissage inventé serait pire qu'un champ libre).
 * Nullable : les zones déjà créées avant ce champ n'ont simplement pas de
 * pays renseigné, elles restent valides telles quelles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_delivery_zones', function (Blueprint $table) {
            $table->string('country')->nullable()->after('menu_id');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_delivery_zones', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
