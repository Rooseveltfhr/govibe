<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (HM-2) — types de chambre et plans tarifaires (nuit / moment) avec ce qui est inclus.
 *
 * `tagtoa_hotel_room_types` : « Chambre climatisée », « Suite »… (ce que le client choisit).
 * `tagtoa_hotel_rate_plans` : un prix pour une NUIT ou pour un MOMENT (1 h, 2 h, 3 h…), dans une devise, avec la liste
 *   de ce qui est inclus (`inclusions` : clés du catalogue + libellés libres).
 *
 * Aucune réservation ici (HM-3) : la page publique AFFICHE. Jamais supprimés, seulement désactivés (les futures
 * réservations figeront le prix et les inclusions qu'elles ont vus). Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_hotel_room_types')) {
            Schema::create('tagtoa_hotel_room_types', function (Blueprint $t) {
                $t->id();
                $t->string('tenant_id', 64);
                $t->string('name', 80);
                $t->string('description', 300)->nullable();
                $t->unsignedSmallInteger('capacity')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'is_active', 'sort'], 'hotel_room_types_tenant_idx');
            });
        }

        if (! Schema::hasTable('tagtoa_hotel_rate_plans')) {
            Schema::create('tagtoa_hotel_rate_plans', function (Blueprint $t) {
                $t->id();
                $t->string('tenant_id', 64);
                $t->unsignedBigInteger('room_type_id');
                $t->string('kind', 8);                       // night | block
                $t->unsignedSmallInteger('hours')->nullable(); // pour un « moment » : 1, 2, 3…
                $t->decimal('price', 14, 2);
                $t->string('currency', 10);
                $t->json('inclusions')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();

                $t->index(['tenant_id', 'room_type_id', 'is_active'], 'hotel_rate_plans_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_hotel_rate_plans');
        Schema::dropIfExists('tagtoa_hotel_room_types');
    }
};
