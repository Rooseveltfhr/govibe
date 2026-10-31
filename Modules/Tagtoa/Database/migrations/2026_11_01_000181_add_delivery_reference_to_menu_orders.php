<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le « repère » de livraison, figé sur la commande.
 *
 * En Haïti (et ailleurs) beaucoup de maisons n'ont ni numéro ni rue connue :
 * ce qui retrouve vraiment la porte, c'est un repère — « en face du bâtiment »,
 * « à côté de l'église ». Texte libre, facultatif, jamais indexé.
 *
 * Rejouable : ne fait rien si la colonne existe déjà (un déploiement relancé
 * après un échec ne doit jamais casser sur « Duplicate column »).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_menu_orders') || Schema::hasColumn('tagtoa_menu_orders', 'delivery_reference')) {
            return;
        }

        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->string('delivery_reference', 200)->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_menu_orders') && Schema::hasColumn('tagtoa_menu_orders', 'delivery_reference')) {
            Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
                $table->dropColumn('delivery_reference');
            });
        }
    }
};
