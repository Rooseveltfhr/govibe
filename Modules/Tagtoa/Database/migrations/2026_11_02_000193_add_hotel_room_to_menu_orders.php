<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H5b) — une commande Menu passée depuis une chambre (room service).
 *
 * `tagtoa_menu_orders.hotel_room_id` : NULL pour toute commande ordinaire (rien ne change pour l'existant).
 * Pas de clé étrangère (la chambre n'est jamais supprimée, seulement désactivée). Index nommé court
 * (limite MySQL de 64 caractères) pour compter les commandes ouvertes d'une chambre.
 *
 * Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_menu_orders') && ! Schema::hasColumn('tagtoa_menu_orders', 'hotel_room_id')) {
            Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
                $t->unsignedBigInteger('hotel_room_id')->nullable();
                $t->index(['hotel_room_id', 'status'], 'menu_orders_room_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_menu_orders') && Schema::hasColumn('tagtoa_menu_orders', 'hotel_room_id')) {
            Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
                $t->dropIndex('menu_orders_room_status_idx');
                $t->dropColumn('hotel_room_id');
            });
        }
    }
};
