<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA Billing — date de règlement d'une commission (payout/settlement).
 * Colonne nullable ajoutée à une table tagtoa_* (règle DB respectée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_commissions', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_commissions', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_commissions', 'settled_at')) {
                $table->dropColumn('settled_at');
            }
        });
    }
};
