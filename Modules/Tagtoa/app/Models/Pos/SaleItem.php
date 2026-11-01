<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA POS — ligne de vente.
 */
class SaleItem extends Model
{
    protected $table = 'tagtoa_pos_sale_items';

    protected $fillable = ['sale_id', 'product_id', 'source', 'name', 'price', 'cost_price', 'qty', 'line_total', 'tax_rate', 'tax_amount', 'currency_original', 'unit_price_original', 'rate_used', 'price_check'];

    protected $casts = ['price' => 'decimal:2', 'cost_price' => 'decimal:2', 'qty' => 'float', 'line_total' => 'decimal:2', 'tax_rate' => 'float', 'tax_amount' => 'decimal:2', 'unit_price_original' => 'decimal:2', 'rate_used' => 'decimal:6'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
