<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * TAGTOA MENU — produit ou service vendu (appartient à une catégorie).
 */
class Item extends Model
{
    protected $table = 'tagtoa_menu_items';

    protected $fillable = [
        'menu_id', 'category_id', 'name', 'description', 'price', 'image_path',
        'emoji', 'badge', 'is_available', 'is_featured', 'stock', 'sort',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured'  => 'boolean',
        'stock'        => 'integer',
        'sort'         => 'integer',
    ];

    /** Disponible à la vente : stock non suivi (null) OU stock > 0. */
    public function getInStockAttribute(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }
}
