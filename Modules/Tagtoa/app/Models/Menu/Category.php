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

    protected $fillable = ['menu_id', 'name', 'icon', 'sort', 'is_active', 'translations', 'section', 'station', 'is_alcohol'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer', 'translations' => 'array', 'is_alcohol' => 'boolean'];

    /** Rayons d'un même menu (hôtel avec restaurant ET bar : un seul menu). */
    public const SECTIONS = ['restaurant' => 'Restaurant', 'bar' => 'Bar', 'other' => 'Autre'];

    /** Où la ligne s'affiche une fois commandée : écran cuisine ou écran bar. */
    public const STATIONS = ['kitchen' => 'Cuisine', 'bar' => 'Bar'];

    /** Valeur sûre : une valeur inconnue retombe sur le défaut, jamais en base telle quelle. */
    public static function cleanSection(?string $v): string
    {
        return array_key_exists((string) $v, self::SECTIONS) ? (string) $v : 'restaurant';
    }

    public static function cleanStation(?string $v): string
    {
        return array_key_exists((string) $v, self::STATIONS) ? (string) $v : 'kitchen';
    }

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

    /**
     * Ce qu'un CLIENT peut voir : disponible ET publié. Un produit « en
     * brouillon » (encore en train d'être rempli) n'apparaît jamais ici,
     * même s'il est par ailleurs marqué disponible — voir la migration
     * is_published pour la distinction avec « disponible/épuisé ».
     */
    public function availableItems(): HasMany
    {
        return $this->items()->where('is_available', true)->where('is_published', true);
    }

    /** Le nom de cette catégorie, dans une langue. Voir Menu::translated(). */
    public function translated(string $champ = 'name', ?string $locale = null): string
    {
        return Translatable::resolve($this->translations, $this->{$champ}, $champ,
            $locale ?? \Modules\Tagtoa\App\Support\Locale::current());
    }
}
