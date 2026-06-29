<?php

namespace Modules\Tagtoa\App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA — abonnement marchand (forfait). Un actif par tenant.
 */
class Subscription extends Model
{
    protected $table = 'tagtoa_subscriptions';

    protected $fillable = ['tenant_id', 'plan', 'status', 'started_at', 'expires_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** Forfait effectif d'un tenant (sinon défaut plateforme). */
    public static function planFor(?string $tenantId): string
    {
        $sub = $tenantId ? static::where('tenant_id', $tenantId)->first() : null;
        $plan = $sub?->plan ?? config('tagtoa.default_plan', 'free');

        return array_key_exists($plan, (array) config('tagtoa.plans', [])) ? $plan : 'free';
    }
}
