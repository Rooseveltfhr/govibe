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

    /** Méthodes supportées : label + icône + région (pour le regroupement UI). */
    public const METHODS = [
        'moncash'  => ['label' => 'MonCash',          'icon' => 'fa-mobile-screen-button', 'region' => 'haiti'],
        'natcash'  => ['label' => 'NatCash',          'icon' => 'fa-mobile-screen-button', 'region' => 'haiti'],
        'cash'     => ['label' => 'Cash',             'icon' => 'fa-money-bill-wave',       'region' => 'local'],
        'zelle'    => ['label' => 'Zelle',            'icon' => 'fa-dollar-sign',          'region' => 'diaspora'],
        'paypal'   => ['label' => 'PayPal',           'icon' => 'fa-paypal',               'region' => 'intl'],
        'card'     => ['label' => 'Carte bancaire',   'icon' => 'fa-credit-card',          'region' => 'intl'],
        'bank'     => ['label' => 'Virement bancaire','icon' => 'fa-building-columns',     'region' => 'intl'],
        'usdt'     => ['label' => 'USDT / Crypto',    'icon' => 'fa-bitcoin-sign',         'region' => 'intl'],
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
