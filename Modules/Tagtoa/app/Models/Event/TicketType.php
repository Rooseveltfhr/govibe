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

    protected $fillable = [
        'event_id', 'name', 'price', 'compare_at_price', 'quantity', 'sold', 'is_active', 'sort',
        'is_vip', 'allowed_gates',
    ];

    protected $casts = [
        'price' => 'decimal:2', 'compare_at_price' => 'decimal:2', 'quantity' => 'integer', 'sold' => 'integer',
        'is_active' => 'boolean', 'is_vip' => 'boolean', 'allowed_gates' => 'array',
    ];

    /**
     * Une porte est-elle autorisée pour ce type de billet ? Liste vide/absente
     * = aucune restriction, TOUTES les portes acceptent — comportement d'avant
     * cette fonctionnalité, donc rétro-compatible pour chaque événement déjà créé.
     * Comparaison insensible à la casse : « vip » et « VIP » sont la même porte.
     */
    public function allowsGate(?string $gate): bool
    {
        if (empty($this->allowed_gates) || $gate === null || $gate === '') {
            return true;
        }

        return in_array(mb_strtolower($gate), array_map('mb_strtolower', $this->allowed_gates), true);
    }

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
