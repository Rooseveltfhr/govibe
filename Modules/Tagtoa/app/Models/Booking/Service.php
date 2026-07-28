<?php

namespace Modules\Tagtoa\App\Models\Booking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA BOOKING — prestation réservable (durée + prix).
 */
class Service extends Model
{
    protected $table = 'tagtoa_booking_services';

    protected $fillable = [
        'booking_page_id', 'name', 'description', 'duration_min', 'price', 'is_active', 'sort',
    ];

    protected $casts = [
        'duration_min' => 'integer',
        'price'        => 'decimal:2',
        'is_active'    => 'boolean',
        'sort'         => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class, 'booking_page_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'service_id');
    }
}
