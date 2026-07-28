<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA PAY — prix FIXE optionnel sur une page/lien de paiement.
 * `amount` NULL = montant libre (le client saisit) ; sinon montant imposé
 * (côté serveur = anti-fraude). Colonne additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_payment_pages', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->nullable()->after('default_currency');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_payment_pages', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
