<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Space extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'capacity',
        'price_per_hour',
        'price_per_day',
        'price_per_month',
        'amenities',
        'floor',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'is_active' => 'boolean',
            'price_per_hour' => 'decimal:2',
            'price_per_day' => 'decimal:2',
            'price_per_month' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
