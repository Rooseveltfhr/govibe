<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H6) — le personnel de l'hôtel et qui a traité une demande.
 *
 * - `tagtoa_staff.hotel_team` : l'ÉQUIPE d'un employé « personnel hôtel » (réception, ménage,
 *   maintenance). Nullable : les employés existants (caisse, cuisine, livraison) ne changent pas.
 * - `tagtoa_hotel_requests.handled_by_staff_id` : l'employé (identifié par son code) qui a traité la
 *   demande ; NULL quand c'est le compte patron qui agit. Nom lu à l'affichage, jamais copié.
 *
 * Colonnes nullables, sans clé étrangère ni index. Rejouable et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_staff') && ! Schema::hasColumn('tagtoa_staff', 'hotel_team')) {
            Schema::table('tagtoa_staff', function (Blueprint $t) {
                $t->string('hotel_team', 30)->nullable();
            });
        }
        if (Schema::hasTable('tagtoa_hotel_requests') && ! Schema::hasColumn('tagtoa_hotel_requests', 'handled_by_staff_id')) {
            Schema::table('tagtoa_hotel_requests', function (Blueprint $t) {
                $t->unsignedBigInteger('handled_by_staff_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_hotel_requests') && Schema::hasColumn('tagtoa_hotel_requests', 'handled_by_staff_id')) {
            Schema::table('tagtoa_hotel_requests', function (Blueprint $t) {
                $t->dropColumn('handled_by_staff_id');
            });
        }
        if (Schema::hasTable('tagtoa_staff') && Schema::hasColumn('tagtoa_staff', 'hotel_team')) {
            Schema::table('tagtoa_staff', function (Blueprint $t) {
                $t->dropColumn('hotel_team');
            });
        }
    }
};
