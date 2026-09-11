<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — une vente sait désormais QUI l'a encaissée.
 *
 * La vente disait sur quelle caisse elle avait eu lieu, jamais par qui. Le
 * patron ne pouvait donc pas savoir qui tenait la caisse 1, et Jacqueline ne
 * pouvait pas voir ses propres ventes.
 *
 * `nullOnDelete` volontairement, et non `cascade` : quand un employé quitte le
 * commerce, ses ventes RESTENT. Elles appartiennent au commerce, pas à lui —
 * effacer une personne ne doit jamais effacer une recette encaissée.
 *
 * Colonne facultative : les ventes déjà enregistrées n'ont pas de caissier, et
 * un commerce sans employé continue de vendre exactement comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_pos_sales', 'staff_id')) {
                $table->foreignId('staff_id')->nullable()->after('terminal_id')
                    ->constrained('tagtoa_staff')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_sales', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_pos_sales', 'staff_id')) {
                $table->dropConstrainedForeignId('staff_id');
            }
        });
    }
};
