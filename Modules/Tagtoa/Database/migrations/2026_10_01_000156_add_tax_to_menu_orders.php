<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — la taxe FIGÉE sur la commande.
 *
 * `tax_rate` existe déjà sur `tagtoa_menu_items` et `tagtoa_businesses`
 * depuis les migrations 2026_09_17_000129/000130, mais MenuOrderService ne
 * l'appliquait nulle part : un commerce qui active la taxe pour sa caisse
 * ne la voyait jamais sur ses commandes QR — écart comptable et risque de
 * conformité fiscale.
 *
 * Même principe que le prix d'achat sur la ligne (et que la caisse POS,
 * migration 2026_09_17_000131, dont ceci est le pendant côté Menu) : le
 * taux est celui du jour de la commande, écrit une fois pour toutes. Si le
 * commerce relève son taux demain, les commandes d'aujourd'hui ne doivent
 * pas se recalculer toutes seules — ce sont des pièces comptables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_menu_orders', 'tax_total')) {
                $t->decimal('tax_total', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'tax_base')) {
                $t->decimal('tax_base', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'tax_inclusive')) {
                $t->boolean('tax_inclusive')->default(true);
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'tax_label')) {
                $t->string('tax_label', 24)->nullable();
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'tax_breakdown')) {
                $t->json('tax_breakdown')->nullable();
            }
        });

        Schema::table('tagtoa_menu_order_items', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_menu_order_items', 'tax_rate')) {
                $t->decimal('tax_rate', 6, 3)->nullable();
            }
            if (! Schema::hasColumn('tagtoa_menu_order_items', 'tax_amount')) {
                $t->decimal('tax_amount', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
            foreach (['tax_total', 'tax_base', 'tax_inclusive', 'tax_label', 'tax_breakdown'] as $c) {
                if (Schema::hasColumn('tagtoa_menu_orders', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('tagtoa_menu_order_items', function (Blueprint $t) {
            foreach (['tax_rate', 'tax_amount'] as $c) {
                if (Schema::hasColumn('tagtoa_menu_order_items', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
