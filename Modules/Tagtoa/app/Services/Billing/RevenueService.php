<?php

namespace Modules\Tagtoa\App\Services\Billing;

use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Billing\RevenueSetting;

/**
 * TAGTOA Billing — calcule et enregistre la commission plateforme sur une vente.
 *
 * 2 sources de revenu (choix marchand) : abonnement (Plan/Subscription existants)
 * et/ou commission sur ventes. Ce service gère le volet commission.
 */
class RevenueService
{
    /** Enregistre une commission si le modèle de revenu le prévoit (idempotent par source). */
    public function record(
        string $sourceType,
        int $sourceId,
        string $module,
        float $grossAmount,
        ?string $tenantId = null,
        string $currency = 'HTG'
    ): ?Commission {
        $setting = RevenueSetting::resolve($tenantId);

        if (! $setting->chargesCommission() || ! $setting->appliesToModule($module) || $grossAmount <= 0) {
            return null;
        }

        $existing = Commission::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing) {
            return $existing;
        }

        $commission = $this->computeCommission(
            $grossAmount,
            (float) $setting->commission_percent,
            (float) $setting->commission_fixed
        );

        return Commission::create([
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
            'status'             => Commission::STATUS_ACCRUED,
        ]);
    }

    public function computeCommission(float $gross, float $percent, float $fixed): float
    {
        return min(round($gross * $percent / 100, 2) + $fixed, $gross);
    }
}
