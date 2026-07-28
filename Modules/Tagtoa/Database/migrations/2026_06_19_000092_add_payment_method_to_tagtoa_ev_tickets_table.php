<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — moyen de paiement d'un billet vendu au terminal staff.
 *   Le vendeur choisit le moyen (issu de TAGTOA Pay : MonCash, NatCash, Espèces…).
 * Colonne nullable, conforme à la règle « jamais casser l'existant ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_tickets', 'payment_method')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->string('payment_method')->nullable()->after('holder_phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_tickets', 'payment_method')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};
