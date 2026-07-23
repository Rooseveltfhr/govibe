<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA Event — type de billet (VIP, Standard, Gratuit).
 */
class TicketType extends Model
{
    protected $table = 'tagtoa_ev_ticket_types';

    protected $fillable = ['event_id', 'name', 'price', 'compare_at_price', 'quantity', 'sold', 'is_active', 'sort'];

    protected $casts = [
        'price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'quantity' => 'integer', 'sold' => 'integer', 'is_active' => 'boolean',
    ];

    /** Une réduction est active si un prix barré supérieur au prix courant est défini. */
    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null && (float) $this->compare_at_price > (float) $this->price;
    }

    /** Pourcentage de réduction (0 si aucune). */
    public function getDiscountPercentAttribute(): int
    {
        if (! $this->hasDiscount()) {
            return 0;
        }

        return (int) round((1 - ((float) $this->price / (float) $this->compare_at_price)) * 100);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function getRemainingAttribute(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->sold);
    }

    public function isOnSale(): bool
    {
        return $this->is_active && ($this->remaining === null || $this->remaining > 0);
    }
}
