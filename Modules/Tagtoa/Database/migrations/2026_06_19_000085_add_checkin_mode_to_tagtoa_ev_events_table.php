<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — mode de check-in configurable par événement (billetterie hybride).
 *   qr   = e-billet QR direct (aucune carte physique requise)
 *   nfc  = carte NFC physique (retrait au stand le jour J)
 *   both = les deux acceptés au check-in (défaut recommandé côté produit : qr)
 * Colonne nullable/default, conforme à la règle "jamais casser l'existant".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_events', 'checkin_mode')) {
            Schema::table('tagtoa_ev_events', function (Blueprint $table) {
                $table->string('checkin_mode')->default('qr')->after('notify_email'); // qr | nfc | both
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_events', 'checkin_mode')) {
            Schema::table('tagtoa_ev_events', function (Blueprint $table) {
                $table->dropColumn('checkin_mode');
            });
        }
    }
};
