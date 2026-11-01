<?php

namespace Modules\Tagtoa\App\Models\Currency;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/** TAGTOA DEVISES — journal des changements de taux. Ajout seul : jamais modifié ni supprimé. */
class CurrencyRateHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_currency_rate_history';

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'rate_id', 'code', 'base_currency', 'old_rate', 'new_rate', 'event', 'changed_by'];

    protected $casts = ['old_rate' => 'decimal:6', 'new_rate' => 'decimal:6'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Historique des taux : ajout seul.'));
        static::deleting(fn () => throw new \LogicException('Historique des taux : ajout seul.'));
    }
}
