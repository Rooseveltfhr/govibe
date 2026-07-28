<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — prix barré (avant réduction) sur les types de billets.
 *   compare_at_price = ancien prix affiché barré à côté du prix courant.
 *   Une réduction est visible ⇔ compare_at_price > price.
 * Colonne nullable, conforme à la règle « jamais casser l'existant ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tagtoa_ev_ticket_types', 'compare_at_price')) {
            Schema::table('tagtoa_ev_ticket_types', function (Blueprint $table) {
                $table->decimal('compare_at_price', 12, 2)->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tagtoa_ev_ticket_types', 'compare_at_price')) {
            Schema::table('tagtoa_ev_ticket_types', function (Blueprint $table) {
                $table->dropColumn('compare_at_price');
            });
        }
    }
};
