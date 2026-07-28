<?php

namespace Modules\Tagtoa\App\Models\Booking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * TAGTOA BOOKING — rendez-vous pris par un client (capturé en base).
 */
class Booking extends Model
{
    protected $table = 'tagtoa_bookings';

    /** Cycle de vie d'un rendez-vous. */
    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];

    public const STATUS_META = [
        'pending'   => ['label' => 'En attente', 'pill' => 'a'],
        'confirmed' => ['label' => 'Confirmé',   'pill' => 'g'],
        'completed' => ['label' => 'Honoré',     'pill' => 'g'],
        'cancelled' => ['label' => 'Annulé',     'pill' => 'r'],
    ];

    protected $fillable = [
        'booking_page_id', 'service_id', 'tenant_id', 'reference', 'customer_name',
        'customer_phone', 'customer_email', 'starts_at', 'status', 'note',
        'price', 'currency', 'client_uuid',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'price'     => 'decimal:2',
    ];

    public static function generateReference(): string
    {
        return 'RDV-'.strtoupper(Str::random(8));
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class, 'booking_page_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function getStatusMetaAttribute(): array
    {
        return self::STATUS_META[$this->status] ?? ['label' => ucfirst($this->status), 'pill' => 'n'];
    }
}
