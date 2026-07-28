<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — en-tête de transaction wallet (top_up / purchase / refund / payout).
 * idempotency_key : anti-doublon réseau. Chaque txn génère 2 écritures équilibrées
 * dans tagtoa_ev_wallet_entries (débit + crédit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_wallet_txns', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('event_id')->nullable()->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('type', 16);                       // top_up | purchase | refund | payout | adjustment
            $table->string('reference', 40)->unique();        // UUID public
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->bigInteger('amount_minor');               // > 0
            $table->string('currency', 8)->default('HTG');
            $table->string('status', 12)->default('posted');  // posted | voided
            $table->foreignId('source_account_id')->nullable()->constrained('tagtoa_ev_wallet_accounts')->nullOnDelete();
            $table->foreignId('dest_account_id')->nullable()->constrained('tagtoa_ev_wallet_accounts')->nullOnDelete();
            $table->string('payment_ref')->nullable();        // réf provider (top_up)
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_wallet_txns');
    }
};
