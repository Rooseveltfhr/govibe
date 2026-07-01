<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — grand livre en partie double (IMMUABLE).
 * Chaque transaction écrit >=2 lignes : pour un txn donné, Σ(débits) == Σ(crédits).
 * Aucune update/delete : une correction se fait par une transaction inverse.
 * balance_after fige le solde du compte après l'écriture (piste d'audit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('txn_id')->constrained('tagtoa_ev_wallet_txns')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('tagtoa_ev_wallet_accounts')->cascadeOnDelete();
            $table->string('direction', 6);          // debit | credit
            $table->bigInteger('amount_minor');      // > 0
            $table->bigInteger('balance_after');     // solde du compte après l'écriture
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'id']);
            $table->index('txn_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_wallet_entries');
    }
};
