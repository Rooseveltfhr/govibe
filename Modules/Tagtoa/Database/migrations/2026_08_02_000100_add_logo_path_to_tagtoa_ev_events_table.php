<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — logo de l'organisateur, distinct de la cover (bannière large).
 * Affiché sur les billets imprimables (badges). Colonne nullable, additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_events', 'logo_path')) {
            Schema::table('tagtoa_ev_events', function (Blueprint $table) {
                $table->string('logo_path')->nullable()->after('cover_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_events', 'logo_path')) {
            Schema::table('tagtoa_ev_events', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
