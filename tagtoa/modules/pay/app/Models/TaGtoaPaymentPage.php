<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * TAGTOA PAY — page de paiement publique.
 *
 * @property int    $id
 * @property int    $vcard_id
 * @property string $tenant_id
 * @property string $title
 * @property string $alias
 * @property string $description
 * @property string $default_currency
 * @property bool   $is_active
 * @property int    $views
 */
class TaGtoaPaymentPage extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $table = 'tagtoa_payment_pages';

    protected $fillable = [
        'vcard_id',
        'tenant_id',
        'title',
        'alias',
        'description',
        'default_currency',
        'is_active',
        'views',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'views'     => 'integer',
    ];

    /**
     * Méthodes de paiement supportées par TAGTOA.
     * `region` sert juste au groupement visuel dans le dashboard.
     */
    public const PAYMENT_METHODS = [
        'moncash'  => ['label' => 'MonCash',          'icon' => 'fa-mobile-screen-button', 'region' => 'haiti'],
        'natcash'  => ['label' => 'NatCash',          'icon' => 'fa-mobile-screen-button', 'region' => 'haiti'],
        'cash'     => ['label' => 'Cash',             'icon' => 'fa-money-bill-wave',       'region' => 'local'],
        'cod'      => ['label' => 'Cash on Delivery', 'icon' => 'fa-box-open',             'region' => 'local'],
        'zelle'    => ['label' => 'Zelle',            'icon' => 'fa-dollar-sign',          'region' => 'diaspora'],
        'paypal'   => ['label' => 'PayPal',           'icon' => 'fa-paypal',               'region' => 'intl'],
        'stripe'   => ['label' => 'Stripe / Card',    'icon' => 'fa-credit-card',          'region' => 'intl'],
        'bank'     => ['label' => 'Bank Transfer',    'icon' => 'fa-building-columns',     'region' => 'intl'],
        'crypto'   => ['label' => 'Crypto',           'icon' => 'fa-bitcoin-sign',         'region' => 'intl'],
        'binance'  => ['label' => 'Binance Pay',      'icon' => 'fa-coins',                'region' => 'intl'],
        'coinbase' => ['label' => 'Coinbase',         'icon' => 'fa-ethereum',             'region' => 'intl'],
    ];

    /**
     * Génère un alias unique à partir d'un texte (ex. nom du vcard).
     */
    public static function generateAlias(string $base): string
    {
        $slug  = Str::slug($base) ?: 'pay';
        $alias = $slug;
        $i     = 1;

        while (static::withoutTenancy()->where('alias', $alias)->exists()) {
            $alias = $slug . '-' . (++$i);
        }

        return $alias;
    }

    /** Évite une erreur si le scope tenant n'est pas booté (ex. seeders / cli). */
    protected static function withoutTenancy()
    {
        $query = static::query();

        if (method_exists($query, 'withoutTenancy')) {
            return $query->withoutTenancy();
        }

        return $query;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class);
    }

    public function methods(): HasMany
    {
        return $this->hasMany(TaGtoaPaymentMethod::class, 'payment_page_id')->orderBy('sort');
    }

    public function activeMethods(): HasMany
    {
        return $this->methods()->where('is_active', true);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(TaGtoaPaymentProof::class, 'payment_page_id')->latest();
    }

    public function pendingProofs(): HasMany
    {
        return $this->proofs()->where('status', TaGtoaPaymentProof::STATUS_PENDING);
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/pay/' . $this->alias);
    }
}
