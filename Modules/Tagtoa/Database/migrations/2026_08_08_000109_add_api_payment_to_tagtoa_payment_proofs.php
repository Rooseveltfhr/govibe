<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA API — relie une preuve de paiement au paiement API qui l'a demandée.
 *
 * Sans ce lien, approuver une preuve ne pourrait pas repasser le paiement du
 * site tiers en « payé ». Colonne nullable et additive : les preuves normales
 * (page de paiement classique) restent inchangées.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_payment_proofs', 'api_payment_id')) {
            Schema::table('tagtoa_payment_proofs', function (Blueprint $table) {
                $table->foreignId('api_payment_id')->nullable()->after('payment_method_id')
                    ->constrained('tagtoa_api_payments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_payment_proofs', 'api_payment_id')) {
            Schema::table('tagtoa_payment_proofs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('api_payment_id');
            });
        }
    }
};
