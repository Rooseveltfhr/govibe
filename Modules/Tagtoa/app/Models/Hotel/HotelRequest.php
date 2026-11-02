<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules;

/**
 * TAGTOA HÔTEL — une demande d'invité. Isolée par commerce. `track_hash` ne sort jamais en JSON.
 */
class HotelRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_requests';

    protected $fillable = [
        'tenant_id', 'room_id', 'service_id', 'reference', 'track_hash', 'kind', 'service_name', 'team',
        'status', 'note', 'reason', 'handled_at', 'closed_at',
    ];

    protected $hidden = ['track_hash'];

    protected $casts = ['handled_at' => 'datetime', 'closed_at' => 'datetime'];

    public function getStatusLabelAttribute(): string
    {
        return HotelRequestRules::STATUSES[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, HotelRequestRules::OPEN, true);
    }
}
