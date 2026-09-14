<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA POS — une ligne rendue.
 *
 * Elle porte le nom et le prix FIGÉS au moment du retour, comme la ligne de
 * vente porte les siens : un article renommé ou dont le prix change demain ne
 * doit pas réécrire un remboursement d'hier.
 */
class SaleReturnItem extends Model
{
    protected $table = 'tagtoa_pos_return_items';

    protected $fillable = [
        'return_id', 'sale_item_id', 'product_id', 'source',
        'name', 'price', 'qty', 'line_total', 'tax_amount',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'qty'        => 'float',
        'line_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }
}
