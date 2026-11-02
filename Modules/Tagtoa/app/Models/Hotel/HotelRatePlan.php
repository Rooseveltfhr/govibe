<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomTypeRules;

/**
 * TAGTOA HÔTEL (HM-2) — un prix pour une NUIT ou un MOMENT (1 h, 2 h…), avec ce qui est inclus.
 */
class HotelRatePlan extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_rate_plans';

    protected $fillable = ['tenant_id', 'room_type_id', 'kind', 'hours', 'price', 'currency', 'inclusions', 'sort', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'hours' => 'integer', 'inclusions' => 'array', 'sort' => 'integer', 'is_active' => 'boolean'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    /** « Nuit » ou « 2 h ». */
    public function getDurationLabelAttribute(): string
    {
        return $this->kind === 'block' ? ($this->hours.' h') : __('Nuit');
    }

    /** Ce qui est inclus, prêt à afficher : [libellé, icône] (clés du catalogue d'abord, puis libellés libres). */
    public function inclusionItems(): array
    {
        return HotelRoomTypeRules::describe($this->inclusions);
    }
}
