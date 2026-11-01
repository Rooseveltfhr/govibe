<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA PAY (PAY-3) — devises acceptées par moyen de paiement.
 *
 * `currencies` = liste JSON de codes (« ["HTG","USD"] »). NULL ou liste vide = toutes les devises :
 * tous les moyens existants gardent exactement leur comportement. Une colonne nullable, sans index.
 *
 * Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_payment_methods') && ! Schema::hasColumn('tagtoa_payment_methods', 'currencies')) {
            Schema::table('tagtoa_payment_methods', function (Blueprint $table) {
                $table->text('currencies')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_payment_methods') && Schema::hasColumn('tagtoa_payment_methods', 'currencies')) {
            Schema::table('tagtoa_payment_methods', function (Blueprint $table) {
                $table->dropColumn('currencies');
            });
        }
    }
};
