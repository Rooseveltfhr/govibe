<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA EVENT — commande d'achat de billets.
 */
class TaGtoaEvOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 0;
    public const STATUS_PAID      = 1;
    public const STATUS_CANCELLED = 2;
    public const STATUS_REFUNDED  = 3;

    protected $table = 'tagtoa_ev_orders';

    protected $fillable = [
        'event_id', 'reference', 'buyer_name', 'buyer_phone', 'buyer_email',
        'total', 'currency', 'payment_method', 'status', 'paid_at',
    ];

    protected $casts = [
        'total'   => 'decimal:2',
        'status'  => 'integer',
        'paid_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvent::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(TaGtoaEvTicket::class, 'order_id');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'TGE-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('reference', $ref)->exists());
        return $ref;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
