<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Menu\CategoryIcon;

/**
 * TAGTOA POS — un rayon du commerce.
 *
 * Appartient au COMMERCE, pas à la caisse : deux caisses du même commerce
 * vendent les mêmes rayons. Le trait pose l'isolation automatiquement — sans
 * lui, la grille d'un marchand afficherait les rayons du voisin.
 */
class Category extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_pos_categories';

    protected $fillable = ['tenant_id', 'name', 'icon', 'color', 'sort', 'is_active'];

    protected $casts = [
        'sort'      => 'integer',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * La classe Font Awesome à afficher.
     *
     * Déduite du nom quand le marchand n'a rien choisi : presque personne ne
     * choisit, et une barre de rayons sans icônes ne sert à rien de plus qu'une
     * liste de mots.
     */
    public function getIconClassAttribute(): string
    {
        return CategoryIcon::resolve($this->icon, $this->name);
    }

    /** Rayons actifs, dans l'ordre d'affichage du commerce. */
    public function scopeShown($query)
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }
}
