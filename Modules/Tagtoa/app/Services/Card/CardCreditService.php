<?php

namespace Modules\Tagtoa\App\Services\Card;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Card\CardCredit;

/**
 * TAGTOA CARD — crédits d'activation de cartes officielles.
 *
 * Le fondateur/super-admin accorde (vend) des crédits à un tenant ;
 * l'activation d'une carte officielle au-delà du quota du forfait en consomme un.
 * Tout est atomique et tolérant (table absente → 0 crédit).
 */
class CardCreditService
{
    /*
     * Ce service reçoit TOUJOURS le commerce concerné en paramètre : le
     * fondateur accorde des crédits à un AUTRE commerce que le sien. Les
     * requêtes sortent donc explicitement de la portée automatique (allTenants)
     * et se limitent au tenant passé en argument — sans quoi une attribution
     * créerait une ligne en double au lieu de créditer la bonne.
     */
    /** Crédits achetés disponibles (granted - used). Tolérant. */
    public function available(?string $tenantId): int
    {
        try {
            $row = CardCredit::allTenants()->where('tenant_id', (string) $tenantId)->first();

            return $row ? (int) $row->available : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Accorde (ajoute) des crédits à un tenant. Retourne le nouveau solde. */
    public function grant(?string $tenantId, int $qty): int
    {
        $qty = max(1, $qty);

        return DB::transaction(function () use ($tenantId, $qty) {
            $row = CardCredit::allTenants()->firstOrCreate(['tenant_id' => (string) $tenantId], ['granted' => 0, 'used' => 0]);
            $row = CardCredit::allTenants()->whereKey($row->id)->lockForUpdate()->first();
            $row->update(['granted' => (int) $row->granted + $qty]);

            return (int) $row->fresh()->available;
        });
    }

    /** Consomme un crédit (activation). Retourne true si consommé, false si épuisé. */
    public function consume(?string $tenantId, int $qty = 1): bool
    {
        $qty = max(1, $qty);

        return (bool) DB::transaction(function () use ($tenantId, $qty) {
            $row = CardCredit::allTenants()->where('tenant_id', (string) $tenantId)->lockForUpdate()->first();
            if (! $row || $row->available < $qty) {
                return false;
            }
            $row->update(['used' => (int) $row->used + $qty]);

            return true;
        });
    }
}
