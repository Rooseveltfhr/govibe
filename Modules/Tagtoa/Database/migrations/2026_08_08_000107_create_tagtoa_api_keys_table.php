<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA API — clés d'accès développeur (une par intégration).
 *
 * On ne stocke JAMAIS la clé en clair : `key_prefix` (public, sert à retrouver
 * la ligne) + `key_hash` (SHA-256 du jeton complet, comparé en temps constant).
 * Le jeton entier n'est affiché qu'une seule fois, à la création.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name')->nullable();          // « Boutique de Jean », « Site vitrine »…
            $table->string('key_prefix', 24)->unique();  // ex. tag_live_a1b2c3d4
            $table->string('key_hash', 64);              // sha256 du jeton complet
            $table->string('webhook_url')->nullable();   // notification serveur → serveur
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_api_keys');
    }
};
