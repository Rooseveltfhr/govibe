<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H4) — le support physique (Smart Stand) d'une chambre.
 *
 * `tagtoa_hotel_rooms.stand_id` : NULL = pas de support. UNIQUE : un même support ne peut JAMAIS
 * être rattaché à deux chambres (MySQL admet plusieurs NULL sous un index unique : une chambre
 * sans support, ou dont le support a été révoqué, repasse à NULL). Pas de clé étrangère : les
 * stands n'ont pas de portée tenant et vivent dans leur propre module.
 *
 * Rejouable et réversible. Index nommé court (limite MySQL de 64 caractères).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_hotel_rooms') && ! Schema::hasColumn('tagtoa_hotel_rooms', 'stand_id')) {
            Schema::table('tagtoa_hotel_rooms', function (Blueprint $table) {
                $table->unsignedBigInteger('stand_id')->nullable();
                $table->unique('stand_id', 'hotel_rooms_stand_uq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_hotel_rooms') && Schema::hasColumn('tagtoa_hotel_rooms', 'stand_id')) {
            Schema::table('tagtoa_hotel_rooms', function (Blueprint $table) {
                $table->dropUnique('hotel_rooms_stand_uq');
                $table->dropColumn('stand_id');
            });
        }
    }
};
