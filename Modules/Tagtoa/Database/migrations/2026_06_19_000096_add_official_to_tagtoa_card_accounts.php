<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA CARD — marque « officielle » : une carte devient officielle quand un
 * marchand/organisateur autorisé (plan + crédit d'activation) l'active sur la
 * plateforme — sans envoi physique. Colonnes additives, nullable/default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_card_accounts', function (Blueprint $table) {
            $table->boolean('official')->default(false)->after('status');
            $table->timestamp('activated_at')->nullable()->after('official');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_card_accounts', function (Blueprint $table) {
            $table->dropColumn(['official', 'activated_at']);
        });
    }
};
