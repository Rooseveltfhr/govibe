<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — groundwork pour la vérification NTAG424 (SUN/anti-rejeu).
 *
 * Signalé dans docs/NFC_SECURITY.md et l'audit sécurité : le wallet closed-loop
 * (argent réel) authentifie un tap par UID SEUL — clonable. Le CMAC/anti-rejeu
 * (Support/Nfc/AesCmac+Ntag424+SunVerifier) est codé et testé mais DORMANT.
 *
 * `sun_required` : un tag n'est marqué ainsi QUE si une puce NTAG424 a été
 * physiquement provisionnée avec une clé secrète — ça ne change rien tout
 * seul tant qu'aucun tag ne le porte (défaut false, rétro-compatible à 100%).
 * `last_counter` : dernier SDMReadCtr accepté pour CE tag, pour l'anti-rejeu —
 * sans objet pour un tag non protégé.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garde (voir l'incident documenté dans 2026_10_15_000171) : un
        // redéploiement qui a échoué APRÈS cette migration ne doit pas la
        // faire échouer à nouveau sur une colonne déjà posée.
        Schema::table('tagtoa_ev_nfc_tags', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_ev_nfc_tags', 'sun_required')) {
                $table->boolean('sun_required')->default(false)->after('status');
            }
            if (! Schema::hasColumn('tagtoa_ev_nfc_tags', 'last_counter')) {
                $table->unsignedInteger('last_counter')->nullable()->after('sun_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_ev_nfc_tags', function (Blueprint $table) {
            $table->dropColumn(['sun_required', 'last_counter']);
        });
    }
};
