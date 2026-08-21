<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA CARD — sécurité NTAG424 (dormante jusqu'au provisionnement des clés) :
 * `nfc_secure` marque une carte cryptographique (SUN/SDM), `nfc_last_counter`
 * stocke le dernier compteur validé (anti-rejeu). Colonnes additives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_card_accounts', function (Blueprint $table) {
            $table->boolean('nfc_secure')->default(false)->after('official');
            $table->unsignedInteger('nfc_last_counter')->nullable()->after('nfc_secure');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_card_accounts', function (Blueprint $table) {
            $table->dropColumn(['nfc_secure', 'nfc_last_counter']);
        });
    }
};
