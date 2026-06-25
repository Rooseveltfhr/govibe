<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA MENU — ligne de commande (snapshot du produit acheté).
 */
class OrderItem extends Model
{
    protected $table = 'tagtoa_menu_order_items';

    protected $fillable = ['order_id', 'item_id', 'name', 'price', 'qty', 'line_total'];

    protected $casts = [
        'price'      => 'decimal:2',
        'qty'        => 'integer',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
