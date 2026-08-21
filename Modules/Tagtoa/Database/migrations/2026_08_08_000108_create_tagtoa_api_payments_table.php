<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA API — paiement créé par un site tiers via l'API développeur.
 *
 * Le montant est figé à la création (côté serveur) : la page de paiement
 * hébergée ne fait que l'afficher, jamais le recalculer depuis l'URL.
 * `external_id` = la référence de commande du site tiers, pour qu'il puisse
 * réconcilier de son côté ; `client_uuid`-like idempotence via `reference`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_api_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('api_key_id')->nullable()->constrained('tagtoa_api_keys')->nullOnDelete();
            $table->foreignId('payment_page_id')->nullable()->constrained('tagtoa_payment_pages')->nullOnDelete();
            $table->string('reference', 64)->unique();          // TAGTOA : réf publique
            $table->string('external_id')->nullable()->index(); // réf de commande du site tiers
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 8)->default('HTG');
            $table->string('description')->nullable();
            // pending | paid | failed | cancelled
            $table->string('status', 16)->default('pending')->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('return_url')->nullable();   // où renvoyer le client après paiement
            $table->string('callback_url')->nullable(); // notification serveur → serveur
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_api_payments');
    }
};
