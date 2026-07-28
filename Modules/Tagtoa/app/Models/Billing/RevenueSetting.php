<?php

namespace Modules\Tagtoa\App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA Billing — modèle de revenu (par tenant ou défaut plateforme).
 */
class RevenueSetting extends Model
{
    public const MODEL_SUBSCRIPTION = 'subscription';
    public const MODEL_COMMISSION   = 'commission';
    public const MODEL_BOTH         = 'both';

    protected $table = 'tagtoa_revenue_settings';

    protected $fillable = [
        'tenant_id', 'revenue_model', 'commission_percent', 'commission_fixed',
        'currency', 'applies_to', 'is_active',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'commission_fixed'   => 'decimal:2',
        'applies_to'         => 'array',
        'is_active'          => 'boolean',
    ];

    /** Réglage effectif pour un tenant (sinon défaut plateforme, sinon valeurs config). */
    public static function resolve(?string $tenantId): self
    {
        $setting = null;
        if ($tenantId) {
            $setting = static::where('tenant_id', $tenantId)->where('is_active', true)->first();
        }
        $setting ??= static::whereNull('tenant_id')->where('is_active', true)->first();

        return $setting ?? new self([
            'revenue_model'      => config('tagtoa.revenue_model', self::MODEL_SUBSCRIPTION),
            'commission_percent' => (float) config('tagtoa.commission_percent', 0),
            'commission_fixed'   => (float) config('tagtoa.commission_fixed', 0),
            'currency'           => config('tagtoa.default_currency', 'HTG'),
        ]);
    }

    public function chargesCommission(): bool
    {
        return in_array($this->revenue_model, [self::MODEL_COMMISSION, self::MODEL_BOTH], true);
    }

    public function appliesToModule(string $module): bool
    {
        return empty($this->applies_to) || in_array($module, $this->applies_to, true);
    }
}
