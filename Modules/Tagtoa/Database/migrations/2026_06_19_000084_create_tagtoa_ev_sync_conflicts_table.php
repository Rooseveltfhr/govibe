<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — journal des conflits de synchronisation offline.
 *
 * Cas principal : double check-in offline sur 2 appareils. À la sync, le
 * premier gagne ; le second est enregistré ici comme conflit (résolu = false)
 * pour que l'admin le voie. `payload` conserve les données brutes reçues.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('kind')->default('duplicate_checkin'); // duplicate_checkin | ...
            $table->string('client_uuid', 64)->nullable()->index();
            $table->foreignId('ticket_id')->nullable()->constrained('tagtoa_ev_tickets')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('tagtoa_ev_staff')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_sync_conflicts');
    }
};
