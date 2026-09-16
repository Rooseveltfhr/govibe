<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — d'où vient une commande de billets : achetée (défaut, tout
 * l'historique existant) ou invitée (billet offert par l'organisateur à un
 * invité VIP, jamais passé par le panier public).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_ev_orders', function (Blueprint $table) {
            $table->string('source')->default('purchase')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_ev_orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
