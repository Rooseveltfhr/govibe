<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA POS — ligne de vente.
 */
class TaGtoaPosSaleItem extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_pos_sale_items';

    protected $fillable = ['sale_id', 'product_id', 'name', 'price', 'qty', 'line_total'];

    protected $casts = [
        'price'      => 'decimal:2',
        'qty'        => 'integer',
        'line_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPosSale::class, 'sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPosProduct::class, 'product_id');
    }
}
