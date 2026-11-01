<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomRules;

/** TAGTOA HÔTEL — une chambre. Isolée par commerce ; numéro unique dans le commerce. */
class HotelRoom extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_rooms';

    protected $fillable = ['tenant_id', 'number', 'name', 'type', 'floor', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'stand_id' => 'integer'];

    public function getTypeLabelAttribute(): ?string
    {
        return $this->type ? (HotelRoomRules::TYPES[$this->type] ?? null) : null;
    }

    /** « Chambre 101 », ou « Chambre 101 — Vue mer » quand elle a un nom. */
    public function getLabelAttribute(): string
    {
        return __('Chambre').' '.$this->number.($this->name ? ' — '.$this->name : '');
    }
}
