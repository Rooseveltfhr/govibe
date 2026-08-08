<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — groupes d'options par plat (ex. « Taille », « Niveau épicé »).
 * `required` + `multiple` pilotent le rendu (radio vs checkbox) et la validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('tagtoa_menu_items')->cascadeOnDelete();
            $table->string('name'); // ex. "Taille", "Suppléments"
            $table->boolean('required')->default(false);
            $table->boolean('multiple')->default(false); // true = choix multiples (checkbox)
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_item_options');
    }
};
