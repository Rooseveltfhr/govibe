<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'active' => 'boolean'];
    }

    public function isUsable(): bool
    {
        if (! $this->active) {
            return false;
        }
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        return $this->max_uses === null || $this->used < $this->max_uses;
    }

    /** Applique la remise à un montant en HTG. */
    public function apply(int $amount): int
    {
        $discount = $this->type === 'percent'
            ? (int) round($amount * min(100, $this->value) / 100)
            : (int) $this->value;

        return max(0, $amount - $discount);
    }
}
