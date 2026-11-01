<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H2) — les chambres d'un hôtel.
 *
 * Une chambre appartient à UN commerce (`tenant_id`) et son numéro est unique DANS ce
 * commerce : deux hôtels peuvent chacun avoir une chambre « 101 ». Une chambre n'est jamais
 * supprimée (des demandes et des supports y seront rattachés) : on la désactive.
 *
 * Le lien avec le support physique (QR/NFC) arrive avec H4 ; ici, rien qui n'existe pas encore.
 *
 * Rejouable : ne fait rien si la table existe déjà. Index nommé court (limite MySQL de 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_hotel_rooms')) {
            return;
        }

        Schema::create('tagtoa_hotel_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('number', 20);
            $table->string('name', 80)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('floor', 10)->nullable();
            $table->string('notes', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'hotel_rooms_tenant_number_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_hotel_rooms');
    }
};
