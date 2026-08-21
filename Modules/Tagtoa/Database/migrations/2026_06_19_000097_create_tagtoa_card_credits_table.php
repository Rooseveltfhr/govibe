<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA CARD — crédits d'activation de cartes officielles (levier de revenu
 * international, SANS envoi de matériel). Le fondateur/super-admin accorde/vend
 * des crédits à un marchand/revendeur ; chaque activation de carte officielle
 * au-delà du quota du forfait consomme 1 crédit. Disponible = granted - used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_card_credits', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->unsignedInteger('granted')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_card_credits');
    }
};
