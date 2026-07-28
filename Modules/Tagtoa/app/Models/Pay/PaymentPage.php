<?php

namespace Modules\Tagtoa\App\Models\Pay;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA Pay — page de paiement publique (tagtoa.com/pay/{alias}).
 */
class PaymentPage extends Model
{
    protected $table = 'tagtoa_payment_pages';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'title', 'alias', 'description',
        'default_currency', 'is_active', 'views',
    ];

    protected $casts = ['is_active' => 'boolean', 'views' => 'integer'];

    /** Méthodes supportées : label + icône (classe FA complète) + région (groupe UI). */
    public const METHODS = [
        // Haïti — mobile money
        'moncash'     => ['label' => 'MonCash',                  'icon' => 'fa-solid fa-mobile-screen-button', 'region' => 'haiti'],
        'natcash'     => ['label' => 'NatCash',                  'icon' => 'fa-solid fa-mobile-screen-button', 'region' => 'haiti'],
        'lajancash'   => ['label' => 'LajanCash',               'icon' => 'fa-solid fa-mobile-screen-button', 'region' => 'haiti'],
        // Haïti — virements bancaires
        'unibank'     => ['label' => 'Virement Unibank',        'icon' => 'fa-solid fa-building-columns',      'region' => 'haiti'],
        'sogebank'    => ['label' => 'Virement Sogebank',       'icon' => 'fa-solid fa-building-columns',      'region' => 'haiti'],
        'bnc'         => ['label' => 'Virement BNC',            'icon' => 'fa-solid fa-building-columns',      'region' => 'haiti'],
        'capitalbank' => ['label' => 'Virement Capital Bank',   'icon' => 'fa-solid fa-building-columns',      'region' => 'haiti'],
        'buh'         => ['label' => 'Virement BUH',            'icon' => 'fa-solid fa-building-columns',      'region' => 'haiti'],
        // Cash
        'cash'        => ['label' => 'Cash',                     'icon' => 'fa-solid fa-money-bill-wave',       'region' => 'local'],
        'cod'         => ['label' => 'Cash on Delivery',        'icon' => 'fa-solid fa-box-open',              'region' => 'local'],
        // Diaspora / international
        'zelle'       => ['label' => 'Zelle',                    'icon' => 'fa-solid fa-dollar-sign',           'region' => 'diaspora'],
        'cashapp'     => ['label' => 'Cash App',                'icon' => 'fa-solid fa-dollar-sign',           'region' => 'diaspora'],
        'venmo'       => ['label' => 'Venmo',                    'icon' => 'fa-solid fa-dollar-sign',           'region' => 'diaspora'],
        'paypal'      => ['label' => 'PayPal',                   'icon' => 'fa-brands fa-paypal',               'region' => 'intl'],
        'card'        => ['label' => 'Carte (VISA/Mastercard)', 'icon' => 'fa-solid fa-credit-card',           'region' => 'intl'],
        'bank_intl'   => ['label' => 'Virement international',   'icon' => 'fa-solid fa-building-columns',       'region' => 'intl'],
        'wise'        => ['label' => 'Wise',                     'icon' => 'fa-solid fa-money-bill-transfer',   'region' => 'intl'],
        // Crypto
        'usdt'        => ['label' => 'USDT (Tether)',           'icon' => 'fa-solid fa-dollar-sign',           'region' => 'crypto'],
        'usdc'        => ['label' => 'USDC',                     'icon' => 'fa-solid fa-dollar-sign',           'region' => 'crypto'],
        'btc'         => ['label' => 'Bitcoin (BTC)',          'icon' => 'fa-brands fa-bitcoin',              'region' => 'crypto'],
        'eth'         => ['label' => 'Ethereum (ETH)',         'icon' => 'fa-brands fa-ethereum',             'region' => 'crypto'],
        'binance'     => ['label' => 'Binance Pay',            'icon' => 'fa-solid fa-coins',                 'region' => 'crypto'],
        'crypto'      => ['label' => 'Autre crypto',           'icon' => 'fa-solid fa-coins',                 'region' => 'crypto'],
        // TAGTOA
        'tagtoa_card' => ['label' => 'Carte TAGTOA',           'icon' => 'fa-solid fa-id-card',               'region' => 'tagtoa'],
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'pay';
        $alias = $slug; $i = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug.'-'.(++$i);
        }
        return $alias;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class, 'vcard_id');
    }

    public function methods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'payment_page_id')->orderBy('sort');
    }

    public function activeMethods(): HasMany
    {
        return $this->methods()->where('is_active', true);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'payment_page_id')->latest();
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/pay/'.$this->alias);
    }
}
