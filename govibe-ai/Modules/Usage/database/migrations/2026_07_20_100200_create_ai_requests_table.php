<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'utilisation : une ligne par appel IA, y compris les échecs et les
 * bascules. C'est la source de vérité pour la facturation (Phase 2), les
 * tableaux de bord (Phase 3) et le diagnostic des fournisseurs.
 *
 * Table à forte croissance : les lectures de tableau de bord passeront par
 * l'agrégat `usage_daily` (Phase 3), et la table sera partitionnée par mois
 * en Phase 8 (échelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // Rattachement facultatif tant qu'ApiPlatform/Core ne sont pas livrés.
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();

            $table->string('capability');            // chat, images, speech…
            $table->string('provider_key');
            $table->string('model_key');
            $table->string('status');                // ok | failed | failover
            $table->string('routed_reason')->nullable(); // pourquoi ce candidat
            $table->string('error_code')->nullable();

            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cost_micro')->default(0); // coût de revient
            $table->unsignedInteger('attempts')->default(1);
            $table->boolean('streamed')->default(false);
            $table->string('language', 8)->nullable();

            $table->string('idempotency_key')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['provider_key', 'created_at']);
            $table->index('status');
            $table->unique(['organization_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
