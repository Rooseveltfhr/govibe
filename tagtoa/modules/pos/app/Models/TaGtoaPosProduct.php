<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA POS — produit (1 bouton = 1 article : emoji + couleur).
 */
class TaGtoaPosProduct extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_pos_products';

    protected $fillable = [
        'terminal_id', 'name', 'price', 'emoji', 'color', 'stock', 'is_active', 'sort',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'stock'     => 'integer',
        'is_active' => 'boolean',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPosTerminal::class, 'terminal_id');
    }
}
