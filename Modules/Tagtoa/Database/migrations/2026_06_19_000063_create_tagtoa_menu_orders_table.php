<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — commandes capturées en base (en plus de WhatsApp).
 * Permet la gestion des commandes + commission plateforme sur ventes payées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('tagtoa_menus')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('reference')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 8)->default('HTG');
            // pending | confirmed | preparing | ready | completed | cancelled
            $table->string('status', 20)->default('pending')->index();
            // unpaid | paid
            $table->string('payment_status', 12)->default('unpaid')->index();
            $table->string('channel', 16)->default('menu'); // menu | whatsapp
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('table_label', 40)->nullable(); // n° table (sur place)
            $table->text('note')->nullable();
            $table->string('client_uuid', 64)->nullable()->unique(); // idempotence
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_orders');
    }
};
