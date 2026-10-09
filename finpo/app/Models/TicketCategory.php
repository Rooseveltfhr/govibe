<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'benefits'    => 'array',
            'active'      => 'boolean',
            'sales_start' => 'datetime',
            'sales_end'   => 'datetime',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /** Nombre de places restantes (null = illimité). */
    public function remaining(): ?int
    {
        if ($this->quota === null) {
            return null;
        }

        $used = $this->registrations()->where('status', '!=', 'cancelled')->count();

        return max(0, $this->quota - $used);
    }

    /** La catégorie est-elle achetable maintenant ? */
    public function isOnSale(): bool
    {
        if (! $this->active) {
            return false;
        }
        if ($this->sales_start && now()->lt($this->sales_start)) {
            return false;
        }
        if ($this->sales_end && now()->gt($this->sales_end)) {
            return false;
        }

        return $this->remaining() === null || $this->remaining() > 0;
    }

    public function isFree(): bool
    {
        return (int) $this->price === 0;
    }
}
