<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H7) — index des requêtes les plus fréquentes.
 *
 * - `tagtoa_hotel_requests (tenant_id, team, status, id)` : l'alerte de la tablette du personnel (max(id) + compte,
 *   toutes les 15 s par tablette) et la liste filtrée par équipe.
 * - `tagtoa_hotel_requests (created_at)` : la commande de rétention quotidienne.
 *
 * Rejouable et réversible ; noms courts (limite MySQL de 64 caractères).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_hotel_requests')) {
            return;
        }
        Schema::table('tagtoa_hotel_requests', function (Blueprint $t) {
            if (! Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_team_idx')) {
                $t->index(['tenant_id', 'team', 'status', 'id'], 'hotel_requests_team_idx');
            }
            if (! Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_created_idx')) {
                $t->index('created_at', 'hotel_requests_created_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tagtoa_hotel_requests')) {
            return;
        }
        Schema::table('tagtoa_hotel_requests', function (Blueprint $t) {
            if (Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_team_idx')) {
                $t->dropIndex('hotel_requests_team_idx');
            }
            if (Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_created_idx')) {
                $t->dropIndex('hotel_requests_created_idx');
            }
        });
    }
};
