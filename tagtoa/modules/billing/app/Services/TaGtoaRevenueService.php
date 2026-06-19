<?php

namespace App\Services;

use App\Models\TaGtoaCommission;
use App\Models\TaGtoaRevenueSetting;
use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA BILLING — calcule et enregistre la commission plateforme sur une vente.
 *
 * Modèle de revenu (2 options au choix du marchand / défaut plateforme) :
 *   - 'subscription' : aucune commission (le marchand paie un abonnement).
 *   - 'commission'   : commission % + frais fixe sur chaque vente.
 *   - 'both'         : abonnement + commission (souvent réduite).
 *
 * Usage :
 *   app(TaGtoaRevenueService::class)->record('event_order', $order->id, 'event', $order->total, $tenantId, 'HTG');
 */
class TaGtoaRevenueService
{
    /**
     * Enregistre une commission pour une vente, si le modèle de revenu le prévoit.
     * Idempotent par (source_type, source_id). Retourne la ligne ou null.
     */
    public function record(
        string $sourceType,
        int $sourceId,
        string $module,
        float $grossAmount,
        ?string $tenantId = null,
        string $currency = 'HTG'
    ): ?TaGtoaCommission {
        $setting = TaGtoaRevenueSetting::resolve($tenantId);

        if (! $setting->chargesCommission() || ! $setting->appliesToModule($module) || $grossAmount <= 0) {
            return null;
        }

        // Évite les doublons (ex. webhook rejoué).
        $existing = TaGtoaCommission::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
        if ($existing) {
            return $existing;
        }

        $commission = $this->computeCommission(
            $grossAmount,
            (float) $setting->commission_percent,
            (float) $setting->commission_fixed
        );

        return TaGtoaCommission::create([
            'tenant_id'          => $tenantId,
            'source_type'        => $sourceType,
            'source_id'          => $sourceId,
            'module'             => $module,
            'gross_amount'       => $grossAmount,
            'commission_amount'  => $commission,
            'net_amount'         => round($grossAmount - $commission, 2),
            'commission_percent' => $setting->commission_percent,
            'commission_fixed'   => $setting->commission_fixed,
            'currency'           => $currency ?: $setting->currency,
            'status'             => TaGtoaCommission::STATUS_ACCRUED,
        ]);
    }

    /** Calcul brut → commission (% + fixe), bornée au montant brut. */
    public function computeCommission(float $gross, float $percent, float $fixed): float
    {
        $commission = round($gross * $percent / 100, 2) + $fixed;

        return min(round($commission, 2), $gross);
    }

    /**
     * Helper pour les modèles : déduit tenant/currency d'un modèle source si possible.
     */
    public function recordFromModel(Model $source, string $sourceType, string $module, float $gross): ?TaGtoaCommission
    {
        $tenantId = $source->tenant_id ?? ($source->event->tenant_id ?? null);
        $currency = $source->currency ?? 'HTG';

        return $this->record($sourceType, $source->getKey(), $module, $gross, $tenantId, $currency);
    }
}
