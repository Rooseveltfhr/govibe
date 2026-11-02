<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomTypeRules;

/**
 * TAGTOA HÔTEL (HM-3) — une demande de réservation. Isolée par commerce. `track_hash` ne sort jamais en JSON.
 * Prix, devise, total et « inclus » sont GELÉS à la création.
 */
class HotelReservation extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_reservations';

    protected $fillable = [
        'tenant_id', 'reference', 'track_hash', 'room_type_id', 'rate_plan_id', 'room_type_name', 'plan_kind', 'plan_hours',
        'price', 'currency', 'nights', 'total', 'inclusions', 'starts_on', 'start_time', 'guests',
        'guest_name', 'guest_phone', 'phone_key', 'note', 'status', 'reason', 'handled_at', 'closed_at',
    ];

    protected $hidden = ['track_hash'];

    protected $casts = [
        'price' => 'decimal:2', 'total' => 'decimal:2', 'nights' => 'integer', 'plan_hours' => 'integer', 'guests' => 'integer',
        'inclusions' => 'array', 'starts_on' => 'date', 'handled_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    public function getStatusLabelAttribute(): string
    {
        return HotelReservationRules::STATUSES[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, HotelReservationRules::OPEN, true);
    }

    /** « Nuit » ou « 2 h » (gelé: ne suit pas le plan s'il change ensuite). */
    public function getPlanLabelAttribute(): string
    {
        return $this->plan_kind === 'block' ? ($this->plan_hours.' h') : __('Nuit');
    }

    /** Ce qui était inclus au moment de la demande. */
    public function inclusionItems(): array
    {
        return HotelRoomTypeRules::describe($this->inclusions);
    }
}
