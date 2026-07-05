<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — traçabilité : quel staff a exécuté le check-in.
 * Colonne nullable (règle "nouvelles colonnes nullable/default", jamais casser).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_checkins', 'staff_id')) {
            Schema::table('tagtoa_ev_checkins', function (Blueprint $table) {
                $table->foreignId('staff_id')->nullable()->after('gate')
                    ->constrained('tagtoa_ev_staff')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_checkins', 'staff_id')) {
            Schema::table('tagtoa_ev_checkins', function (Blueprint $table) {
                $table->dropConstrainedForeignId('staff_id');
            });
        }
    }
};
