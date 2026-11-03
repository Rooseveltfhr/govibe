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
    /**
     * Comment la vente a été encaissée — décide si TAGTOA peut la facturer.
     *
     * Une commission ne se prélève que sur un encaissement que TAGTOA peut VÉRIFIER lui-même :
     *  - ONLINE_VERIFIED : passerelle en ligne, confirmée par webhook / réconciliation (jamais par le retour du client) ;
     *  - LEDGER          : débit d'une carte TAGTOA, écrit dans notre propre grand livre.
     * Jamais sur les espèces, ni sur un paiement « confirmé à la main » par le marchand (MonCash saisi à la caisse,
     * virement…) : rien de vérifiable à facturer, et le marchand déclarerait lui-même sa dette.
     * Par défaut (canal non précisé) : AUCUNE commission — un nouvel appelant doit déclarer son canal.
     */
    public const CHANNEL_ONLINE_VERIFIED = 'online_verified';
    public const CHANNEL_LEDGER = 'ledger';
    public const CHANNEL_CASH = 'cash';
    public const CHANNEL_MANUAL = 'manual';
    public const CHANNEL_UNKNOWN = 'unknown';

    public const COMMISSIONABLE_CHANNELS = [self::CHANNEL_ONLINE_VERIFIED, self::CHANNEL_LEDGER];

    /** Enregistre une commission si le modèle de revenu le prévoit ET si l'encaissement est vérifiable (idempotent par source). */
    public function record(
        string $sourceType,
        int $sourceId,
        string $module,
        float $grossAmount,
        ?string $tenantId = null,
        string $currency = 'HTG',
        string $channel = self::CHANNEL_UNKNOWN
    ): ?Commission {
        if (! in_array($channel, self::COMMISSIONABLE_CHANNELS, true)) {
            return null;
        }

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
