<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
        'quantity',
        'min_quantity',
        'unit',
        'location',
        'purchase_price',
        'value',
        'supplier',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'min_quantity' => 'decimal:3',
            'purchase_price' => 'decimal:2',
            'value' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }
}
