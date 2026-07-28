<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA Pay — institution + logo affichés au client (passerelle / banque).
 * Colonnes nullable sur notre table tagtoa_* (règle DB respectée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_payment_methods', 'institution')) {
                $table->string('institution')->nullable()->after('account_holder');
            }
            if (! Schema::hasColumn('tagtoa_payment_methods', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('qr_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_payment_methods', function (Blueprint $table) {
            foreach (['institution', 'logo_path'] as $col) {
                if (Schema::hasColumn('tagtoa_payment_methods', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
