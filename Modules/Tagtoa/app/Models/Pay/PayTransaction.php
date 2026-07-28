<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * TAGTOA PAY — transaction de paiement en ligne (passerelle API).
 */
class PayTransaction extends Model
{
    protected $table = 'tagtoa_pay_transactions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'gateway', 'reference', 'gateway_ref', 'order_type', 'order_id',
        'amount', 'currency', 'status', 'meta', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'meta'    => 'array',
        'paid_at' => 'datetime',
    ];

    public static function generateReference(): string
    {
        return 'TX-'.strtoupper(Str::random(10));
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
