<?php

namespace Modules\Tagtoa\App\Support;

use Illuminate\Http\RedirectResponse;
use Modules\Tagtoa\App\Services\Billing\PlanService;

/**
 * TAGTOA — garde de forfait pour les contrôleurs dashboard.
 * Utilisation : if ($r = $this->planGuard('site')) return $r;
 */
trait EnforcesPlan
{
    protected function planGuard(string $feature): ?RedirectResponse
    {
        if (app(PlanService::class)->canCreate(Tenant::id(), $feature)) {
            return null;
        }

        return redirect()->route('tagtoa.plan.index')
            ->with('error', __('Limite de votre forfait atteinte pour ce module. Passez à un forfait supérieur pour continuer.'));
    }
}
