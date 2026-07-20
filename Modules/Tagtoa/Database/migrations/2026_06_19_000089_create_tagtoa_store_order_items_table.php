<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA STORE — lignes de commande (snapshot du produit acheté).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('tagtoa_store_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_store_order_items');
    }
};
