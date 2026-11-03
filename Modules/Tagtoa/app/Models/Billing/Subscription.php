<?php

namespace Modules\Tagtoa\App\Models\Billing;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA — abonnement marchand (forfait). Un actif par tenant.
 *
 * Expiration (R0-a) : `coverage()` dit où en est l'abonnement ; `planFor()` n'en tient compte QUE si
 * `tagtoa.enforce_expiry` est allumé. Un abonnement sans date de fin (forfait gratuit, accord manuel) ne s'éteint jamais.
 */
class Subscription extends Model
{
    use BelongsToTenant;

    /** Où en est l'abonnement : en cours / bientôt échu / échu mais en grâce / échu pour de bon. */
    public const COVER_ACTIVE = 'active';
    public const COVER_EXPIRING = 'expiring';
    public const COVER_GRACE = 'grace';
    public const COVER_EXPIRED = 'expired';

    /** Prévenir le marchand ce nombre de jours avant la fin. */
    public const EXPIRING_DAYS = 7;

    protected $table = 'tagtoa_subscriptions';

    protected $fillable = ['tenant_id', 'plan', 'status', 'started_at', 'expires_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function expiryEnforced(): bool
    {
        return (bool) config('tagtoa.enforce_expiry', false);
    }

    public static function graceDays(): int
    {
        return max(0, (int) config('tagtoa.expiry_grace_days', 7));
    }

    /** Jours entiers restants avant la fin (arrondi vers le haut ; négatif ou nul si échu). */
    public function daysLeft(?Carbon $now = null): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }
        $now ??= now();

        return (int) ceil(($this->expires_at->getTimestamp() - $now->getTimestamp()) / 86400);
    }

    public function coverage(?Carbon $now = null): string
    {
        if ($this->expires_at === null) {
            return self::COVER_ACTIVE;
        }
        $now ??= now();
        if ($now->lte($this->expires_at)) {
            return $this->daysLeft($now) <= self::EXPIRING_DAYS ? self::COVER_EXPIRING : self::COVER_ACTIVE;
        }

        return $now->lte($this->expires_at->copy()->addDays(self::graceDays())) ? self::COVER_GRACE : self::COVER_EXPIRED;
    }

    /** Statuts qui donnent droit au forfait (l'essai en fait partie ; « expired », « cancelled »… non). */
    public const GRANTING_STATUSES = ['active', 'trial'];

    /** Le forfait de cet abonnement s'applique-t-il encore ? (statut qui donne droit ET pas échu au-delà de la grâce) */
    public function grantsPlan(?Carbon $now = null): bool
    {
        return in_array($this->status, self::GRANTING_STATUSES, true) && $this->coverage($now) !== self::COVER_EXPIRED;
    }

    /**
     * Forfait « sur devis » (prix NULL) : négocié à la main, pas renouvelable en libre-service. Il n'est JAMAIS rétrogradé
     * automatiquement — ce sont les plus gros clients ; l'administrateur les traite à la main (le rapport les liste).
     */
    public function isQuoteOnly(): bool
    {
        $cfg = (array) (\Modules\Tagtoa\App\Services\Billing\PlanService::effectivePlans()[$this->plan] ?? []);

        return array_key_exists('price', $cfg) && $cfg['price'] === null;
    }

    /** Forfait effectif d'un tenant (sinon défaut plateforme). */
    public static function planFor(?string $tenantId): string
    {
        $sub = $tenantId ? static::where('tenant_id', $tenantId)->first() : null;
        $plan = $sub?->plan ?? config('tagtoa.default_plan', 'free');

        // Échu au-delà de la grâce (ou statut non actif) : retour au forfait par défaut — seulement si le drapeau est allumé.
        if ($sub && static::expiryEnforced() && ! $sub->grantsPlan() && ! $sub->isQuoteOnly()) {
            $plan = config('tagtoa.default_plan', 'free');
        }

        return array_key_exists($plan, (array) config('tagtoa.plans', [])) ? $plan : 'free';
    }
}
