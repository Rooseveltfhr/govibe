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

    protected $fillable = ['sale_id', 'product_id', 'name', 'price', 'qty', 'line_total'];

    protected $casts = ['price' => 'decimal:2', 'qty' => 'integer', 'line_total' => 'decimal:2'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
