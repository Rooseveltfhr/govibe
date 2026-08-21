<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — traçabilité des billets pré-imprimés (hors système) importés
 * en masse pour être vendus au terminal staff, + vendeur/date de vente pour
 * TOUT billet vendu sur le terrain (imprimé ou e-billet).
 *   - serial            : second code imprimé au dos du billet physique (secours
 *                         si le QR est illisible).
 *   - imported          : billet enregistré depuis un lot pré-imprimé (pas créé
 *                         par le système) — reste vrai même après vente.
 *   - sold_by_staff_id  : membre du staff qui a émis/vendu le billet (terminal).
 *   - sold_at           : horodatage de la vente.
 * Colonnes nullable/default, additives — conforme à la règle « jamais casser
 * l'existant ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_tickets', 'serial')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->string('serial', 40)->nullable()->after('code');
            });
        }
        if (! Schema::hasColumn('tagtoa_ev_tickets', 'imported')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->boolean('imported')->default(false)->after('serial');
            });
        }
        if (! Schema::hasColumn('tagtoa_ev_tickets', 'sold_by_staff_id')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->foreignId('sold_by_staff_id')->nullable()->after('payment_method')
                    ->constrained('tagtoa_ev_staff')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('tagtoa_ev_tickets', 'sold_at')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->timestamp('sold_at')->nullable()->after('sold_by_staff_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_tickets', 'sold_by_staff_id')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sold_by_staff_id');
            });
        }
        Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
            foreach (['serial', 'imported', 'sold_at'] as $col) {
                if (Schema::hasColumn('tagtoa_ev_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
