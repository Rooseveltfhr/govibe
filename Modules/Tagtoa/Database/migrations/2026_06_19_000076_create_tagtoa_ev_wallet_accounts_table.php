<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — comptes de valeur (wallet closed-loop).
 * balance_minor = SOLDE CACHÉ (unités mineures, entier). La VÉRITÉ reste la somme
 * des écritures du grand livre (tagtoa_ev_wallet_entries).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('event_id')->nullable()->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->foreignId('nfc_tag_id')->nullable()->constrained('tagtoa_ev_nfc_tags')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tagtoa_ev_tickets')->nullOnDelete();
            $table->string('type', 16);                       // participant | vendor | organizer | gateway_clearing | house
            $table->string('owner_label')->nullable();
            $table->string('currency', 8)->default('HTG');
            $table->bigInteger('balance_minor')->default(0);  // cache (unités mineures)
            $table->string('status', 12)->default('active');  // active | frozen | closed
            $table->timestamps();

            $table->index(['event_id', 'type']);
            $table->unique('nfc_tag_id');                     // 1 tag = 1 compte (NULLs multiples OK en MySQL)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_wallet_accounts');
    }
};
