<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA HÔTEL (HM-2) — un type de chambre que le client peut choisir. Isolé par commerce ; jamais supprimé (désactivé).
 */
class HotelRoomType extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_room_types';

    protected $fillable = ['tenant_id', 'name', 'description', 'capacity', 'sort', 'is_active'];

    protected $casts = ['capacity' => 'integer', 'sort' => 'integer', 'is_active' => 'boolean'];

    public function plans(): HasMany
    {
        return $this->hasMany(HotelRatePlan::class, 'room_type_id')->orderBy('sort')->orderBy('id');
    }
}
