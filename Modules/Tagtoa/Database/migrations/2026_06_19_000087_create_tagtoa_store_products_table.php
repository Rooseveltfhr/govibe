<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA STORE — produits d'une boutique. Prix imposé serveur (anti-tampering).
 * `stock` null = illimité (StockService). `compare_price` = prix barré (promo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('tagtoa_stores')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->string('category')->nullable();
            $table->integer('stock')->nullable();            // null = illimité
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['store_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_store_products');
    }
};
