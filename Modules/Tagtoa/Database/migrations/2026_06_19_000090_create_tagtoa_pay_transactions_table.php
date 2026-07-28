<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA PAY — transactions de paiement en ligne (passerelles API : MonCash…).
 *
 * Relie une commande (order_type + order_id : store|menu|event) à un paiement
 * gateway. Idempotent : une transaction PAYÉE marque la commande payée une
 * seule fois. `gateway_ref` = identifiant chez la passerelle (token/transactionId).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pay_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('gateway', 32);                 // moncash|stripe|paypal…
            $table->string('reference', 64)->unique();     // notre orderId envoyé à la passerelle
            $table->string('gateway_ref')->nullable();     // token / transaction_id côté passerelle
            $table->string('order_type', 24);              // store|menu|event
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('HTG');
            $table->string('status', 16)->default('pending'); // pending|paid|failed
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['order_type', 'order_id']);
            $table->index(['gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pay_transactions');
    }
};
