<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — e-mail de l'organisateur pour les alertes (check-in, etc.).
 * Colonne nullable, conforme à la règle "nouvelles colonnes nullable/default".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_events', 'notify_email')) {
            Schema::table('tagtoa_ev_events', function (Blueprint $table) {
                $table->string('notify_email')->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_events', 'notify_email')) {
            Schema::table('tagtoa_ev_events', function (Blueprint $table) {
                $table->dropColumn('notify_email');
            });
        }
    }
};
