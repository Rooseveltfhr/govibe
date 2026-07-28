<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — tags NFC (carte / bracelet / virtuel) reliés à un billet/wallet.
 * L'UID NFC n'est JAMAIS stocké en clair : on indexe son hash (uid_hash) et on
 * conserve éventuellement une version chiffrée (uid_enc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_nfc_tags', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('uid_hash', 64)->unique();        // SHA-256 de l'UID
            $table->text('uid_enc')->nullable();             // UID chiffré (Crypt) si réémission
            $table->string('label')->nullable();
            $table->string('kind', 12)->default('card');     // card | wristband | virtual
            $table->foreignId('ticket_id')->nullable()->constrained('tagtoa_ev_tickets')->nullOnDelete();
            $table->string('status', 12)->default('active'); // active | lost | disabled
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_nfc_tags');
    }
};
