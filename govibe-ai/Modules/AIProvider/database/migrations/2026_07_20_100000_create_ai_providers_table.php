<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fournisseurs connus de la plateforme. La source de vérité du *catalogue*
 * reste le manifeste du connecteur ; cette table porte l'état opérationnel
 * modifiable depuis l'administration (activation, priorité, surcharges).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('status')->default('active'); // active | degraded | disabled
            $table->string('base_url_override')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
