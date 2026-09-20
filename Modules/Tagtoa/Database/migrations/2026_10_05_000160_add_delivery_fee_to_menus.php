<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — la livraison était offerte sans jamais rien facturer : le
 * mode « Livraison » existait déjà sur la commande (`order_type`), mais
 * aucun frais ne s'y ajoutait jamais, quel que soit le commerce. Un
 * restaurant qui livre au coursier de sa poche ne pouvait pas répercuter ce
 * coût sans en discuter à part (WhatsApp, en espèces à la livraison).
 *
 * `delivery_fee` sur le menu est le réglage ; `delivery_fee` sur la commande
 * en est la COPIE figée au moment de la commande — même principe que
 * `tax_total`/`tax_inclusive` déjà copiés dessus : changer le tarif du
 * commerce demain ne doit jamais changer le sens d'une commande déjà passée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $t) {
            $t->decimal('delivery_fee', 12, 2)->nullable()->default(0);
        });
        Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
            $t->decimal('delivery_fee', 12, 2)->nullable()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $t) {
            $t->dropColumn('delivery_fee');
        });
        Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
            $t->dropColumn('delivery_fee');
        });
    }
};
