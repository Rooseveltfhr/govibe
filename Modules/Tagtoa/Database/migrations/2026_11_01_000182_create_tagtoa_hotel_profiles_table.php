<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H1) — ce qu'un hôtel a de PROPRE, en 1:1 avec son commerce.
 *
 * Le commerce (`tagtoa_businesses`) garde tout ce qui est commun à tous les types :
 * nom, adresse, téléphone, devise, taxes, logo. Cette table ne porte que ce qui n'a de
 * sens que pour un hôtel, et n'est remplie que si `type = hotel` : les autres types
 * d'établissement ne la touchent jamais.
 *
 * `tenant_id` EST l'identifiant du commerce (le commerce est le tenant) et il est UNIQUE :
 * un commerce, un profil d'hôtel. Les champs sont tous facultatifs ; les défauts sont sûrs.
 *
 * Rejouable : ne fait rien si la table existe déjà (un déploiement relancé après un échec
 * ne doit jamais casser sur « table already exists »).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_hotel_profiles')) {
            return;
        }

        Schema::create('tagtoa_hotel_profiles', function (Blueprint $table) {
            $table->id();
            // Nom court de l'index : MySQL limite à 64 caractères.
            $table->string('tenant_id', 64)->unique('hotel_profiles_tenant_uq');

            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedSmallInteger('rooms_count')->nullable();

            $table->string('reception_phone', 40)->nullable();
            $table->string('reception_email', 120)->nullable();
            $table->boolean('reception_24h')->default(true);
            $table->string('reception_hours', 80)->nullable();

            $table->json('facilities')->nullable();      // liste blanche : HotelCatalog::FACILITIES
            $table->json('languages')->nullable();       // codes de langue supportés par TAGTOA
            $table->boolean('room_service_enabled')->default(false);
            $table->text('guest_info')->nullable();      // Wi-Fi, consignes, contacts utiles montrés à l'invité

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_hotel_profiles');
    }
};
