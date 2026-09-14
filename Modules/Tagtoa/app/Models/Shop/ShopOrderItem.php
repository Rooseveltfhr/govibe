<?php

namespace Modules\Tagtoa\App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BOUTIQUE TAGTOA — une ligne de commande, figée au moment du clic.
 *
 * Le nom et le prix sont RECOPIÉS, pas référencés : TAGTOA qui augmente le prix
 * du stand le mois prochain, ou qui renomme un article, ne doit pas réécrire un
 * bon de commande déjà envoyé au marchand.
 */
class ShopOrderItem extends Model
{
    protected $table = 'tagtoa_shop_order_items';

    protected $fillable = ['order_id', 'item_id', 'sku', 'name', 'unit_price', 'qty', 'line_total'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'qty'        => 'integer',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'order_id');
    }
}
