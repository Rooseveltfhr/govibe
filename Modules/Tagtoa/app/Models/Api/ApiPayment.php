<?php

namespace Modules\Tagtoa\App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA API — paiement demandé par un site tiers.
 */
class ApiPayment extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_CANCELLED,
    ];

    protected $table = 'tagtoa_api_payments';

    protected $fillable = [
        'tenant_id', 'api_key_id', 'payment_page_id', 'reference', 'external_id',
        'amount', 'currency', 'description', 'status',
        'customer_name', 'customer_phone', 'customer_email',
        'return_url', 'callback_url', 'meta', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'meta'    => 'array',
        'paid_at' => 'datetime',
    ];

    public static function generateReference(): string
    {
        return 'API-'.strtoupper(Str::random(12));
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'payment_page_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** URL publique de la page de paiement hébergée par TAGTOA. */
    public function getCheckoutUrlAttribute(): string
    {
        return url('/pay/i/'.$this->reference);
    }

    /** Représentation JSON stable renvoyée par l'API (contrat public). */
    public function toApiArray(): array
    {
        return [
            'reference'    => $this->reference,
            'external_id'  => $this->external_id,
            'amount'       => (float) $this->amount,
            'currency'     => $this->currency,
            'description'  => $this->description,
            'status'       => $this->status,
            'checkout_url' => $this->checkout_url,
            'paid_at'      => optional($this->paid_at)->toIso8601String(),
            'created_at'   => optional($this->created_at)->toIso8601String(),
        ];
    }
}
