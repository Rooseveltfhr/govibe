<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA Event — commande de billets.
 */
class Order extends Model
{
    public const STATUS_PENDING = 0;
    public const STATUS_PAID = 1;
    public const STATUS_CANCELLED = 2;

    /** Commande née du panier public — l'historique entier avant cette fonctionnalité. */
    public const SOURCE_PURCHASE = 'purchase';
    /** Billet offert par l'organisateur à un invité (VIP, presse, staff…) — jamais payé. */
    public const SOURCE_INVITE = 'invite';

    protected $table = 'tagtoa_ev_orders';

    protected $fillable = [
        'event_id', 'reference', 'buyer_name', 'buyer_phone', 'buyer_email',
        'total', 'currency', 'payment_method', 'status', 'paid_at', 'source',
    ];

    protected $casts = ['total' => 'decimal:2', 'status' => 'integer', 'paid_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'order_id');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'TGE-'.strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());
        return $ref;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isInvite(): bool
    {
        return $this->source === self::SOURCE_INVITE;
    }
}
