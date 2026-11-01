<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA DEVISES (CUR-4) — devise par type de billet, comme pour Menu (CUR-1) et la boutique (CUR-3).
 *
 * - `tagtoa_ev_ticket_types.currency` : NULL = devise de l'événement (rien ne change pour l'existant).
 * - `tagtoa_ev_tickets` : le billet fige le prix d'origine, sa devise et le taux utilisé.
 *
 * Colonnes nullables, sans index. Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_ev_ticket_types') && ! Schema::hasColumn('tagtoa_ev_ticket_types', 'currency')) {
            Schema::table('tagtoa_ev_ticket_types', function (Blueprint $table) {
                $table->string('currency', 8)->nullable();
            });
        }
        if (Schema::hasTable('tagtoa_ev_tickets') && ! Schema::hasColumn('tagtoa_ev_tickets', 'currency_original')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->string('currency_original', 8)->nullable();
                $table->decimal('unit_price_original', 14, 2)->nullable();
                $table->decimal('rate_used', 18, 6)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_ev_tickets') && Schema::hasColumn('tagtoa_ev_tickets', 'currency_original')) {
            Schema::table('tagtoa_ev_tickets', function (Blueprint $table) {
                $table->dropColumn(['currency_original', 'unit_price_original', 'rate_used']);
            });
        }
        if (Schema::hasTable('tagtoa_ev_ticket_types') && Schema::hasColumn('tagtoa_ev_ticket_types', 'currency')) {
            Schema::table('tagtoa_ev_ticket_types', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
