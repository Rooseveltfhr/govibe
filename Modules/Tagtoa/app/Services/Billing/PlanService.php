<?php

namespace Modules\Tagtoa\App\Services\Billing;

use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Site\Site;

/**
 * TAGTOA — forfaits & gating. Calcule limites (config) vs usage (DB) par tenant.
 */
class PlanService
{
    /** feature => modèle (pour compter l'usage du tenant). */
    private const MODELS = [
        'site'    => Site::class,
        'menu'    => Menu::class,
        'pay'     => PaymentPage::class,
        'links'   => LinkPage::class,
        'loyalty' => Program::class,
        'event'   => Event::class,
        'pos'     => Terminal::class,
    ];

    public function planKey(?string $tenantId): string
    {
        return Subscription::planFor($tenantId);
    }

    public function plan(?string $tenantId): array
    {
        $key = $this->planKey($tenantId);
        $cfg = (array) config('tagtoa.plans.'.$key, []);
        $cfg['key'] = $key;

        return $cfg;
    }

    /** Limite d'une feature (null = illimité, 0 = bloqué). */
    public function limit(?string $tenantId, string $feature)
    {
        $limits = (array) config('tagtoa.plans.'.$this->planKey($tenantId).'.limits', []);

        return array_key_exists($feature, $limits) ? $limits[$feature] : null;
    }

    /** Nb de ressources déjà créées par le tenant pour cette feature. */
    public function usage(?string $tenantId, string $feature): int
    {
        $model = self::MODELS[$feature] ?? null;
        if (! $model) {
            return 0;
        }
        try {
            return (int) $model::query()->where('tenant_id', $tenantId)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Le tenant peut-il créer une ressource de plus ? */
    public function canCreate(?string $tenantId, string $feature): bool
    {
        $limit = $this->limit($tenantId, $feature);
        if ($limit === null) {
            return true; // illimité
        }

        return $this->usage($tenantId, $feature) < (int) $limit;
    }

    /** Features connues (pour l'affichage usage/limites). */
    public function features(): array
    {
        return array_keys(self::MODELS);
    }
}
