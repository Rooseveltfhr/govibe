<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (HM-3) — demandes de réservation de chambre (nuit / moment), SANS paiement en ligne.
 *
 * L'invité n'a pas de compte : `reference` + un secret de suivi dont seul le sha256 est stocké (`track_hash`), comme les
 * demandes d'invité (H5a). Le PRIX et ce qui est INCLUS sont GELÉS au moment de la demande (`price`, `currency`, `total`,
 * `inclusions`, `room_type_name`, `plan_kind`, `plan_hours`) : changer le tarif plus tard ne modifie jamais une demande
 * déjà faite. `guest_name` / `guest_phone` / `note` sont effacés à la purge (données personnelles) ; la ligne reste.
 *
 * Aucune clé étrangère (même convention que le module). Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_hotel_reservations')) {
            return;
        }

        Schema::create('tagtoa_hotel_reservations', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id', 64);
            $t->string('reference', 16)->unique('hotel_resv_ref_uq');
            $t->char('track_hash', 64);

            $t->unsignedBigInteger('room_type_id')->nullable();
            $t->unsignedBigInteger('rate_plan_id')->nullable();
            $t->string('room_type_name', 80);
            $t->string('plan_kind', 8);                        // night | block
            $t->unsignedSmallInteger('plan_hours')->nullable();
            $t->decimal('price', 14, 2);                        // prix unitaire gelé (une nuit, ou le moment)
            $t->string('currency', 10);
            $t->unsignedSmallInteger('nights')->default(1);
            $t->decimal('total', 14, 2);                        // calculé côté serveur, gelé
            $t->json('inclusions')->nullable();

            $t->date('starts_on');
            $t->time('start_time')->nullable();                 // « moment » seulement
            $t->unsignedSmallInteger('guests')->nullable();

            $t->string('guest_name', 120)->nullable();          // nullable: effacé à la purge
            $t->string('guest_phone', 40)->nullable();
            $t->string('phone_key', 16)->nullable();            // 8 derniers chiffres: plafond / doublon sans lire le numéro
            $t->string('note', 300)->nullable();

            $t->string('status', 20)->default('new');          // new | confirmed | refused | cancelled
            $t->string('reason', 200)->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'status', 'id'], 'hotel_resv_tenant_status_idx');
            $t->index(['tenant_id', 'phone_key', 'status'], 'hotel_resv_phone_idx');
            $t->index('created_at', 'hotel_resv_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_hotel_reservations');
    }
};
