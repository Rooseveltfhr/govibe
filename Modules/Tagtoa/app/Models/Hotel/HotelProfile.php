<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA HÔTEL — le profil propre à un hôtel (1:1 avec son commerce, `tenant_id` unique).
 *
 * Isolé par commerce comme tout modèle du module. Accès hors commerce courant : uniquement
 * par HotelProfileService, qui reçoit le commerce en paramètre.
 */
class HotelProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_profiles';

    protected $fillable = [
        'tenant_id', 'description', 'cover_path', 'rooms_count',
        'reception_phone', 'reception_email', 'reception_24h', 'reception_hours',
        'facilities', 'languages', 'room_service_enabled', 'guest_info',
    ];

    protected $casts = [
        'rooms_count'          => 'integer',
        'reception_24h'        => 'boolean',
        'room_service_enabled' => 'boolean',
        'facilities'           => 'array',
        'languages'            => 'array',
    ];

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
