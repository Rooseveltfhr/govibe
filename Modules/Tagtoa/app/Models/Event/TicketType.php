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

    protected $fillable = ['event_id', 'name', 'price', 'quantity', 'sold', 'is_active', 'sort'];

    protected $casts = [
        'price' => 'decimal:2', 'quantity' => 'integer', 'sold' => 'integer', 'is_active' => 'boolean',
    ];

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
