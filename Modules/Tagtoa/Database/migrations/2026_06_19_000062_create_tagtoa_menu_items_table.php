<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — produits & services vendus (un item appartient à une catégorie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('tagtoa_menus')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('tagtoa_menu_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('image_path')->nullable();
            $table->string('emoji', 16)->nullable();
            $table->string('badge', 30)->nullable(); // ex. Nouveau, Promo, Populaire
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_items');
    }
};
