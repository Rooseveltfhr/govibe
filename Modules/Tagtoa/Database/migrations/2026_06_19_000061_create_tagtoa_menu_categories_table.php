<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — catégories (ex. Entrées, Plats, Boissons, Services).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('tagtoa_menus')->cascadeOnDelete();
            $table->string('name');
            $table->string('icon', 16)->nullable(); // emoji
            $table->unsignedTinyInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_categories');
    }
};
