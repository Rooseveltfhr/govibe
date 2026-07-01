<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — téléphone du porteur (pour notifications WhatsApp/SMS).
 * Colonne nullable, conforme à la règle "nouvelles colonnes nullable/default".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_wallet_accounts', 'owner_phone')) {
            Schema::table('tagtoa_ev_wallet_accounts', function (Blueprint $table) {
                $table->string('owner_phone', 40)->nullable()->after('owner_label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_wallet_accounts', 'owner_phone')) {
            Schema::table('tagtoa_ev_wallet_accounts', function (Blueprint $table) {
                $table->dropColumn('owner_phone');
            });
        }
    }
};
