<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA STORE — commandes boutique (capturées en base). Idempotent via client_uuid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_store_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('tagtoa_stores')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('reference', 40)->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 8)->default('HTG');
            $table->string('status')->default('pending');           // pending|confirmed|shipped|completed|cancelled
            $table->string('payment_status')->default('unpaid');    // unpaid|paid
            $table->string('channel')->default('store');            // store|whatsapp
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_address')->nullable();         // livraison
            $table->text('note')->nullable();
            $table->string('client_uuid', 64)->nullable()->unique();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_store_orders');
    }
};
