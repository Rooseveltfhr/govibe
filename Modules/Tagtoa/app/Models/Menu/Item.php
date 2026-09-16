<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Support\Catalog\HasCommercialFields;
use Modules\Tagtoa\App\Support\Menu\Translatable;

/**
 * TAGTOA MENU — produit ou service vendu (appartient à une catégorie).
 */
class Item extends Model
{
    use HasCommercialFields;
    protected $table = 'tagtoa_menu_items';

    protected $fillable = [
        'menu_id', 'category_id', 'name', 'description', 'price', 'cost_price', 'unit', 'low_stock_threshold', 'sku', 'supplier_id', 'tax_rate', 'image_path',
        'emoji', 'badge', 'specs', 'is_available', 'is_featured', 'stock', 'sort',
        'translations',
    ];

    protected $casts = [
        // Champs propres au métier (capacité d'une chambre, degré d'alcool,
        // temps de préparation…). Toujours écrits via BusinessProfile::sanitize.
        'specs'        => 'array',
        'price'        => 'decimal:2',
        'cost_price'   => 'decimal:2',
        // Quantités décimales — même règle que côté caisse (StockService).
        'low_stock_threshold' => 'float',
        'is_available' => 'boolean',
        'is_featured'  => 'boolean',
        'stock'        => 'float',
        'sort'         => 'integer',
        'translations' => 'array',
    ];

    /** Le prix, la photo, le stock ne se traduisent PAS : un seul plat, un seul prix. */
    public const CHAMPS_TRADUISIBLES = ['name', 'description'];

    /** Disponible à la vente : stock non suivi (null) OU stock > 0. */
    /** Le fournisseur habituel. Nullable : rien ne dépend de sa présence. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\Modules\Tagtoa\App\Models\Inventory\Supplier::class, 'supplier_id');
    }

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

    public function options(): HasMany
    {
        return $this->hasMany(ItemOption::class, 'item_id')->orderBy('sort');
    }

    /** Le nom ou la description de cet article, dans une langue. Voir Menu::translated(). */
    public function translated(string $champ, ?string $locale = null): string
    {
        return Translatable::resolve($this->translations, $this->{$champ}, $champ,
            $locale ?? \Modules\Tagtoa\App\Support\Locale::current());
    }
}
