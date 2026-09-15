<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\Menu\Translatable;

/**
 * TAGTOA MENU — catégorie (Entrées, Plats, Boissons, Services…).
 */
class Category extends Model
{
    protected $table = 'tagtoa_menu_categories';

    protected $fillable = ['menu_id', 'name', 'icon', 'sort', 'is_active', 'translations'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer', 'translations' => 'array'];

    /** Seul le nom d'une catégorie se traduit — une icône ne change pas de langue. */
    public const CHAMPS_TRADUISIBLES = ['name'];

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

    /** Le nom de cette catégorie, dans une langue. Voir Menu::translated(). */
    public function translated(string $champ = 'name', ?string $locale = null): string
    {
        return Translatable::resolve($this->translations, $this->{$champ}, $champ,
            $locale ?? \Modules\Tagtoa\App\Support\Locale::current());
    }
}
