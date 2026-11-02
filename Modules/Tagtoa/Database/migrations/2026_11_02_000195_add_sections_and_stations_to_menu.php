<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU (HM-1) — un seul menu, plusieurs rayons : restaurant, bar… (hôtel avec bar ET restaurant).
 *
 * - `categories.section`   : restaurant | bar | other  (défaut restaurant → les menus existants ne changent pas)
 * - `categories.station`   : kitchen | bar              (où la ligne s'affiche : écran cuisine ou écran bar)
 * - `categories.is_alcohol`: rayon d'alcool → confirmation « 18 ans et plus » exigée à la commande
 * - `items.on_request`     : « sur commande » (commandable, mais préparé à la demande)
 * - `order_items.station`  : station GELÉE à la commande (déplacer plus tard une catégorie ne réécrit pas l'histoire)
 *
 * Toutes les colonnes ont un défaut : aucun menu ni commande existants ne change. Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_menu_categories')) {
            Schema::table('tagtoa_menu_categories', function (Blueprint $t) {
                if (! Schema::hasColumn('tagtoa_menu_categories', 'section')) {
                    $t->string('section', 16)->default('restaurant');
                }
                if (! Schema::hasColumn('tagtoa_menu_categories', 'station')) {
                    $t->string('station', 16)->default('kitchen');
                }
                if (! Schema::hasColumn('tagtoa_menu_categories', 'is_alcohol')) {
                    $t->boolean('is_alcohol')->default(false);
                }
            });
        }
        if (Schema::hasTable('tagtoa_menu_items') && ! Schema::hasColumn('tagtoa_menu_items', 'on_request')) {
            Schema::table('tagtoa_menu_items', function (Blueprint $t) {
                $t->boolean('on_request')->default(false);
            });
        }
        if (Schema::hasTable('tagtoa_menu_order_items') && ! Schema::hasColumn('tagtoa_menu_order_items', 'station')) {
            Schema::table('tagtoa_menu_order_items', function (Blueprint $t) {
                $t->string('station', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'tagtoa_menu_categories' => ['section', 'station', 'is_alcohol'],
            'tagtoa_menu_items'      => ['on_request'],
            'tagtoa_menu_order_items' => ['station'],
        ] as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as $col) {
                if (Schema::hasColumn($table, $col)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($col));
                }
            }
        }
    }
};
