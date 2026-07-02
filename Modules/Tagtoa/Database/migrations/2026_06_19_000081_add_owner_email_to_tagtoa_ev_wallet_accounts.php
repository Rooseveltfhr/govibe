<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — e-mail du porteur (notifications achat/recharge par e-mail).
 * Colonne nullable, conforme à la règle "nouvelles colonnes nullable/default".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_wallet_accounts', 'owner_email')) {
            Schema::table('tagtoa_ev_wallet_accounts', function (Blueprint $table) {
                $table->string('owner_email', 160)->nullable()->after('owner_phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_wallet_accounts', 'owner_email')) {
            Schema::table('tagtoa_ev_wallet_accounts', function (Blueprint $table) {
                $table->dropColumn('owner_email');
            });
        }
    }
};
