<?php

namespace Modules\Tagtoa\App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA STORE — ligne de commande (snapshot du produit acheté).
 */
class OrderItem extends Model
{
    protected $table = 'tagtoa_store_order_items';

    protected $fillable = ['order_id', 'product_id', 'name', 'price', 'qty', 'line_total', 'currency_original', 'unit_price_original', 'rate_used'];

    protected $casts = [
        'price'      => 'decimal:2',
        'qty'        => 'integer',
        'line_total' => 'decimal:2',
        'unit_price_original' => 'decimal:2',
        'rate_used'  => 'decimal:6',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
