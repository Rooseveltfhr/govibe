<?php

namespace Modules\Tagtoa\App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * TAGTOA STORE — produit d'une boutique.
 */
class Product extends Model
{
    protected $table = 'tagtoa_store_products';

    protected $fillable = [
        'store_id', 'name', 'description', 'price', 'compare_price', 'image_path',
        'category', 'stock', 'is_available', 'is_featured', 'sort',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'compare_price' => 'decimal:2',
        'stock'         => 'integer',
        'is_available'  => 'boolean',
        'is_featured'   => 'boolean',
        'sort'          => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }
}
