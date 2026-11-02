<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H5) — les demandes d'un invité (ménage, linge, maintenance, réception…).
 *
 * - `reference` : courte, publique (« HR-7K3M9QXA »), UNIQUE. Seule, elle ne donne accès à rien.
 * - `track_hash` : empreinte SHA-256 du secret de suivi (128 bits) remis à l'invité ; le secret
 *   n'est jamais stocké en clair. L'invité n'a pas de compte : référence + secret = son suivi.
 * - `service_name`/`kind`/`team` : copiés à la création — renommer un service ne réécrit pas l'histoire.
 * - Aucune donnée personnelle obligatoire : ni nom ni téléphone.
 *
 * Rejouable : ne fait rien si la table existe déjà. Index nommés courts (limite MySQL de 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_hotel_requests')) {
            return;
        }

        Schema::create('tagtoa_hotel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64);
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('reference', 16)->unique('hotel_requests_ref_uq');
            $table->char('track_hash', 64);
            $table->string('kind', 30);
            $table->string('service_name', 80);
            $table->string('team', 30)->nullable();
            $table->string('status', 20)->default('new');
            $table->string('note', 300)->nullable();
            $table->string('reason', 200)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'hotel_requests_tenant_status_idx');
            $table->index(['room_id', 'status'], 'hotel_requests_room_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_hotel_requests');
    }
};
