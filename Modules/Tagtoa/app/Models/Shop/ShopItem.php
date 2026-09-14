<?php

namespace Modules\Tagtoa\App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * BOUTIQUE TAGTOA — un article que la PLATEFORME vend aux marchands.
 *
 * PAS de trait BelongsToTenant, volontairement : c'est TAGTOA qui vend. Le
 * catalogue appartient à la plateforme, comme un lot de stands — il existe
 * avant qu'aucun marchand ne le regarde, et il est le même pour tous.
 */
class ShopItem extends Model
{
    protected $table = 'tagtoa_shop_items';

    protected $fillable = [
        'sku', 'name', 'description', 'image_path', 'unit_price',
        'min_qty', 'step_qty', 'lead_time_days', 'is_active', 'sort',
    ];

    protected $casts = [
        'unit_price'     => 'decimal:2',
        'min_qty'        => 'integer',
        'step_qty'       => 'integer',
        'lead_time_days' => 'integer',
        'is_active'      => 'boolean',
        'sort'           => 'integer',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    /** Ce qui est réellement commandable, dans l'ordre d'affichage. */
    public function scopeShown($query)
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    /**
     * La quantité la plus proche qui soit RÉELLEMENT commandable. PUR côté
     * effets : ne touche pas la base.
     *
     * Un stand se fabrique et s'expédie par lots. Accepter « 13 » quand on vend
     * par cartons de 10 ferait promettre un envoi qu'on ne sait pas préparer —
     * et c'est au moment de la livraison que le marchand l'apprendrait.
     */
    public function normalizeQty(int $qty): int
    {
        $min  = max(1, (int) $this->min_qty);
        $pas  = max(1, (int) $this->step_qty);

        if ($qty < $min) {
            return $min;
        }

        // On arrondit vers le HAUT : un marchand qui demande 25 par cartons de
        // 10 en veut trois, pas deux. Lui en livrer moins qu'il n'a demandé est
        // la seule erreur qu'il remarquera à coup sûr.
        $au_dessus = (int) ceil(($qty - $min) / $pas) * $pas + $min;

        return $au_dessus;
    }
}
