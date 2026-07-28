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
    /** Crédits achetés disponibles (granted - used). Tolérant. */
    public function available(?string $tenantId): int
    {
        try {
            $row = CardCredit::where('tenant_id', (string) $tenantId)->first();

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
            $row = CardCredit::firstOrCreate(['tenant_id' => (string) $tenantId], ['granted' => 0, 'used' => 0]);
            $row = CardCredit::whereKey($row->id)->lockForUpdate()->first();
            $row->update(['granted' => (int) $row->granted + $qty]);

            return (int) $row->fresh()->available;
        });
    }

    /** Consomme un crédit (activation). Retourne true si consommé, false si épuisé. */
    public function consume(?string $tenantId, int $qty = 1): bool
    {
        $qty = max(1, $qty);

        return (bool) DB::transaction(function () use ($tenantId, $qty) {
            $row = CardCredit::where('tenant_id', (string) $tenantId)->lockForUpdate()->first();
            if (! $row || $row->available < $qty) {
                return false;
            }
            $row->update(['used' => (int) $row->used + $qty]);

            return true;
        });
    }
}
