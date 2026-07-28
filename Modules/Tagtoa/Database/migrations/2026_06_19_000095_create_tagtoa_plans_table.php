<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — définitions de forfaits éditables (prix + limites) qui SURCHARGENT
 * la config `tagtoa.plans`. Vide par défaut → on retombe sur la config (sûr).
 * Permet au fondateur de modifier prix/limites depuis l'admin, sans toucher au code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key', 32)->unique();     // free | pro | enterprise
            $table->string('label')->nullable();
            $table->decimal('price', 12, 2)->nullable();   // null = sur devis
            $table->json('limits')->nullable();            // {site:1, menu:1, …} null = illimité
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_plans');
    }
};
