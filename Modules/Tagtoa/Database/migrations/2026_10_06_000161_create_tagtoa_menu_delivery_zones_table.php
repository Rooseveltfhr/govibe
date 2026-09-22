<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — zones de livraison (ex. Centre-ville, Périphérie, +5km),
 * chacune avec son propre frais. Remplace, pour un menu qui les configure,
 * le frais unique de tagtoa_menus.delivery_fee — qui reste le défaut pour
 * un menu qui n'a défini aucune zone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('tagtoa_menus')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_delivery_zones');
    }
};
