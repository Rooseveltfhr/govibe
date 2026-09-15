<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des modèles. Les colonnes de prix et de qualité surchargent le
 * manifeste : elles permettent d'ajuster tarifs et arbitrages du routeur
 * depuis l'administration, sans déploiement.
 *
 * Prix en micro-USD par million de jetons (entiers — aucun flottant
 * n'intervient dans un calcul monétaire).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('provider_key');
            $table->string('key');
            $table->string('name')->nullable();
            $table->json('capabilities');

            // Prix de revient (ce que le fournisseur nous facture).
            $table->unsignedBigInteger('input_price_per_million')->default(0);
            $table->unsignedBigInteger('output_price_per_million')->default(0);

            // Prix de vente — renseignés en Phase 2 (facturation en crédits).
            $table->unsignedBigInteger('sell_input_price_per_million')->nullable();
            $table->unsignedBigInteger('sell_output_price_per_million')->nullable();

            $table->unsignedBigInteger('context_window')->default(8192);
            $table->unsignedTinyInteger('quality')->default(50);
            $table->json('quality_by_language')->nullable();
            $table->unsignedInteger('expected_latency_ms')->default(2000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider_key', 'key']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
