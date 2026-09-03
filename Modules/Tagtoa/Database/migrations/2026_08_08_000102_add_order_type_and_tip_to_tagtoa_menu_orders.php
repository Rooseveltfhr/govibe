<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — mode de service (sur place/pour pote/livraison) + pourboire.
 * Colonnes nullable/défaut, additives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_menu_orders', 'order_type')) {
                // dine_in | pickup | delivery
                $table->string('order_type', 16)->default('dine_in')->after('channel');
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'delivery_address')) {
                $table->string('delivery_address')->nullable()->after('table_label');
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'tip')) {
                $table->decimal('tip', 12, 2)->default(0)->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            foreach (['order_type', 'delivery_address', 'tip'] as $col) {
                if (Schema::hasColumn('tagtoa_menu_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
