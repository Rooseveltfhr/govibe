<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — comptes staff, scopés par événement (auth terrain par PIN).
 *
 * Multi-tenant : un staff appartient à UN événement précis (event_id), pas à
 * toute la plateforme. Rôles opérationnels : admin | vente | checkin
 * (PAS de rôle POS/marchand dans ce module). Le PIN sert au terrain
 * uniquement ; toute la config/finance reste derrière le vrai login tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('pin_hash');
            $table->string('role')->default('checkin'); // admin | vente | checkin
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable(); // user id du propriétaire
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_staff');
    }
};
