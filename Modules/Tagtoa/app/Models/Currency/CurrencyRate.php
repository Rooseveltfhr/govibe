<?php

namespace Modules\Tagtoa\App\Models\Currency;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA DEVISES — taux EN VIGUEUR : 1 `code` = `rate` `base_currency`, pour un commerce.
 * Ne s'écrit que par CurrencyRateService (qui consigne l'historique).
 */
class CurrencyRate extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_currency_rates';

    protected $fillable = ['tenant_id', 'code', 'base_currency', 'symbol', 'decimals', 'rate', 'is_active', 'updated_by'];

    protected $casts = ['rate' => 'decimal:6', 'decimals' => 'integer', 'is_active' => 'boolean'];

    /** « 5.00 $HT » — formaté avec le symbole et les décimales choisis par le marchand. */
    public function format(float $amount): string
    {
        return number_format($amount, (int) $this->decimals, '.', ',').' '.($this->symbol ?: $this->code);
    }
}
