<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — choix possibles pour un groupe d'options (ex. "Grand" +50 HTG).
 * `price_delta` peut être négatif (ex. retirer un ingrédient n'est jamais un
 * supplément payant, mais on garde la possibilité pour une future promo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_item_option_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')->constrained('tagtoa_menu_item_options')->cascadeOnDelete();
            $table->string('label'); // ex. "Grand", "Piquant", "Fromage extra"
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_item_option_choices');
    }
};
