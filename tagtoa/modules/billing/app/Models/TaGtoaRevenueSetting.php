<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA BILLING — réglage du modèle de revenu (par tenant ou défaut plateforme).
 *
 * @property string $tenant_id
 * @property string $revenue_model      subscription|commission|both
 * @property float  $commission_percent
 * @property float  $commission_fixed
 * @property string $currency
 * @property array  $applies_to
 * @property bool   $is_active
 */
class TaGtoaRevenueSetting extends Model
{
    use HasFactory;

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

    /** Réglage effectif pour un tenant (sinon défaut plateforme, sinon valeurs neutres). */
    public static function resolve(?string $tenantId): self
    {
        $setting = null;

        if ($tenantId) {
            $setting = static::where('tenant_id', $tenantId)->where('is_active', true)->first();
        }
        $setting ??= static::whereNull('tenant_id')->where('is_active', true)->first();

        return $setting ?? new self([
            'revenue_model'      => self::MODEL_SUBSCRIPTION,
            'commission_percent' => 0,
            'commission_fixed'   => 0,
        ]);
    }

    /** TAGTOA prélève-t-il une commission dans ce modèle ? */
    public function chargesCommission(): bool
    {
        return in_array($this->revenue_model, [self::MODEL_COMMISSION, self::MODEL_BOTH], true);
    }

    /** Ce module est-il concerné par la commission ? */
    public function appliesToModule(string $module): bool
    {
        return empty($this->applies_to) || in_array($module, $this->applies_to, true);
    }
}
