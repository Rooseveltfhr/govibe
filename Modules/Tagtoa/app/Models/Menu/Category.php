<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA MENU — catégorie (Entrées, Plats, Boissons, Services…).
 */
class Category extends Model
{
    protected $table = 'tagtoa_menu_categories';

    protected $fillable = ['menu_id', 'name', 'icon', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id')->orderBy('sort');
    }

    public function availableItems(): HasMany
    {
        return $this->items()->where('is_available', true);
    }
}
