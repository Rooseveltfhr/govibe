<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — Extension migration
|--------------------------------------------------------------------------
| Adds the menu-specific fields required by TAGTOA MENU on top of the
| existing whatsapp_store_products / whatsapp_stores tables without
| touching or renaming anything that already exists.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_store_products', function (Blueprint $table) {
            // Discounted price (nullable — shows strikethrough on original price)
            $table->float('discount_price')->nullable()->after('selling_price');

            // Preparation time in minutes (e.g. 15 = "15 min")
            $table->unsignedSmallInteger('prep_time')->nullable()->after('discount_price');

            // Featured badge ("Chef's pick")
            $table->boolean('featured')->default(false)->after('prep_time');

            // Availability toggle — owner can mark "Sold out" without deleting
            $table->boolean('is_available')->default(true)->after('featured');

            // Fulfilment options for this item
            $table->boolean('dine_in')->default(true)->after('is_available');
            $table->boolean('takeout')->default(true)->after('dine_in');
            $table->boolean('delivery')->default(false)->after('takeout');
        });

        Schema::table('whatsapp_stores', function (Blueprint $table) {
            // Business type drives icon + label on TAGTOA MENU (restaurant, hotel, bar, lounge, cafe, club, fastfood)
            $table->string('business_type')->nullable()->default('restaurant')->after('store_name');

            // Whether delivery is offered at all (shown as a badge in header)
            $table->boolean('delivery_available')->default(false)->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_store_products', function (Blueprint $table) {
            $table->dropColumn([
                'discount_price',
                'prep_time',
                'featured',
                'is_available',
                'dine_in',
                'takeout',
                'delivery',
            ]);
        });

        Schema::table('whatsapp_stores', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'delivery_available']);
        });
    }
};
