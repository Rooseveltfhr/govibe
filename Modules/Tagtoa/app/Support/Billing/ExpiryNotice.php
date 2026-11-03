<?php

namespace Modules\Tagtoa\App\Support\Billing;

use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Services\Billing\PlanService;

/**
 * TAGTOA — l'avis « votre forfait expire » du tableau de bord (R0-a).
 *
 * Jamais bloquant : une panne ici ne doit pas casser une page. « Bientôt échu » s'affiche toujours (c'est vrai, que
 * l'expiration soit appliquée ou non) ; « en grâce » / « échu » seulement quand l'expiration est appliquée, sinon on
 * promettrait une conséquence qui n'existe pas.
 */
class ExpiryNotice
{
    /** @return array{state:string,days:int,plan:string,label:string}|null */
    public static function forTenant(?string $tenantId): ?array
    {
        if (! $tenantId) {
            return null;
        }
        try {
            $sub = Subscription::where('tenant_id', $tenantId)->first();
            if (! $sub || $sub->expires_at === null) {
                return null;
            }
            $cfg = (array) (PlanService::effectivePlans()[$sub->plan] ?? []);
            if ((float) ($cfg['price'] ?? 0) <= 0) {
                return null;   // gratuit ou sur devis : pas de renouvellement en libre-service
            }

            $state = $sub->coverage();
            if ($state === Subscription::COVER_ACTIVE) {
                return null;
            }
            if ($state !== Subscription::COVER_EXPIRING && ! Subscription::expiryEnforced()) {
                return null;
            }

            $days = $state === Subscription::COVER_EXPIRING
                ? max(0, $sub->daysLeft())
                : max(0, Subscription::graceDays() - (int) floor((now()->getTimestamp() - $sub->expires_at->getTimestamp()) / 86400));

            return ['state' => $state, 'days' => $days, 'plan' => $sub->plan, 'label' => (string) ($cfg['label'] ?? ucfirst($sub->plan))];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
