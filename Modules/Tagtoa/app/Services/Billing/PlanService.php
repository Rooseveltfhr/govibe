<?php

namespace Modules\Tagtoa\App\Services\Billing;

use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Models\Booking\BookingPage;
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
        'booking' => BookingPage::class,
    ];

    /** Cache des forfaits effectifs pour la durée de la requête. */
    private static ?array $effective = null;

    public function planKey(?string $tenantId): string
    {
        return Subscription::planFor($tenantId);
    }

    /**
     * Forfaits EFFECTIFS = config `tagtoa.plans` surchargée par la table
     * `tagtoa_plans` (édition fondateur). Tolérant : si la table n'existe pas
     * ou est vide, on retombe intégralement sur la config.
     */
    public static function effectivePlans(): array
    {
        if (self::$effective !== null) {
            return self::$effective;
        }

        $plans = (array) config('tagtoa.plans', []);

        try {
            foreach (\Modules\Tagtoa\App\Models\Billing\PlanDefinition::all() as $row) {
                $key = $row->plan_key;
                if (! isset($plans[$key])) {
                    $plans[$key] = [];
                }
                if ($row->label !== null && $row->label !== '') {
                    $plans[$key]['label'] = $row->label;
                }
                // price : la valeur DB fait foi (y compris null = sur devis)
                $plans[$key]['price'] = $row->price === null ? null : (float) $row->price;
                if (is_array($row->limits)) {
                    $plans[$key]['limits'] = array_merge($plans[$key]['limits'] ?? [], $row->limits);
                }
                $plans[$key]['is_active'] = (bool) $row->is_active;
            }
        } catch (\Throwable $e) {
            // table absente / DB indisponible → config seule
        }

        return self::$effective = $plans;
    }

    /** Réinitialise le cache (après une édition des forfaits). */
    public static function flush(): void
    {
        self::$effective = null;
    }

    public function plan(?string $tenantId): array
    {
        $key = $this->planKey($tenantId);
        $cfg = (array) (self::effectivePlans()[$key] ?? []);
        $cfg['key'] = $key;

        return $cfg;
    }

    /** Limite d'une feature (null = illimité, 0 = bloqué). */
    public function limit(?string $tenantId, string $feature)
    {
        $limits = (array) (self::effectivePlans()[$this->planKey($tenantId)]['limits'] ?? []);

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
