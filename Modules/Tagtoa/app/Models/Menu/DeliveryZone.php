<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA MENU — une zone de livraison et son propre frais (ex. Centre-ville,
 * Périphérie, +5km). Un menu qui n'en définit aucune garde le frais unique
 * de Menu::delivery_fee — voir MenuOrderService::placeOrder().
 */
class DeliveryZone extends Model
{
    protected $table = 'tagtoa_menu_delivery_zones';

    protected $fillable = ['menu_id', 'name', 'fee', 'sort', 'is_active'];

    protected $casts = ['fee' => 'decimal:2', 'sort' => 'integer', 'is_active' => 'boolean'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }
}
