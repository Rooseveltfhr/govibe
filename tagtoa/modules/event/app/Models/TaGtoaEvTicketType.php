<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — type de billet (VIP, Standard, Gratuit).
 */
class TaGtoaEvTicketType extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_ev_ticket_types';

    protected $fillable = [
        'event_id', 'name', 'price', 'quantity', 'sold',
        'sale_starts_at', 'sale_ends_at', 'is_active', 'sort',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'quantity'       => 'integer',
        'sold'           => 'integer',
        'sale_starts_at' => 'datetime',
        'sale_ends_at'   => 'datetime',
        'is_active'      => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvent::class, 'event_id');
    }

    public function getRemainingAttribute(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->sold);
    }

    public function isOnSale(): bool
    {
        $now = now();
        if (! $this->is_active) {
            return false;
        }
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }
        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }
        return $this->remaining === null || $this->remaining > 0;
    }
}
