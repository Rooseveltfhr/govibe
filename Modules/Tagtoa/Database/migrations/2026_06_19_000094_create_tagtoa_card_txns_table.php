<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA CARD — grand livre IMMUABLE des mouvements d'une carte (recharge, débit,
 * remboursement). Append-only : chaque ligne porte un instantané du solde après
 * l'opération. reference = idempotence (une même opération ne s'applique qu'une fois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_card_txns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_account_id')->constrained('tagtoa_card_accounts')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();   // marchand qui encaisse (débit)
            $table->string('type', 12);                         // topup | charge | refund | adjust
            $table->bigInteger('amount_minor');                 // toujours positif
            $table->bigInteger('balance_after_minor');          // instantané après opération
            $table->string('currency', 8)->default('HTG');
            $table->string('reference', 40)->unique();          // idempotence
            $table->string('context_type', 24)->nullable();     // pay_page | menu | event | pos | store
            $table->unsignedBigInteger('context_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['card_account_id', 'created_at']);
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_card_txns');
    }
};
