<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modes de service que CE menu offre au client — sous-ensemble de
 * Order::ORDER_TYPES (sur place / à emporter / livraison). Nullable : un
 * menu qui n'a jamais choisi ne restreint rien, même convention que
 * `hours`/`languages` (nul = pas encore réglé, jamais une liste vide qui
 * bloquerait toute commande).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->json('service_types')->nullable()->after('languages');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->dropColumn('service_types');
        });
    }
};
