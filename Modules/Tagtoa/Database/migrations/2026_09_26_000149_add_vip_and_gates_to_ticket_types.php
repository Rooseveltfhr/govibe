<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — un type de billet peut être VIP, et peut être restreint à
 * certaines portes.
 *
 * `is_vip` : simple étiquette jusqu'ici portée par le NOM libre du type
 * (« VIP » tapé à la main) — désormais un vrai indicateur, utilisé pour le
 * message d'accueil au scan et le badge imprimé, sans dépendre d'un texte
 * que l'organisateur pourrait taper autrement (« Vip », « V.I.P »…).
 *
 * `allowed_gates` : JSON, liste de codes de porte (ex. ["A","VIP"]). NULL ou
 * vide = aucune restriction, TOUTES les portes acceptent ce billet — c'est le
 * comportement actuel, donc rétro-compatible pour tout événement existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_ev_ticket_types', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false)->after('is_active');
            $table->json('allowed_gates')->nullable()->after('is_vip');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_ev_ticket_types', function (Blueprint $table) {
            $table->dropColumn(['is_vip', 'allowed_gates']);
        });
    }
};
