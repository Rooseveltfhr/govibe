<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Catalog\HasCommercialFields;

/**
 * TAGTOA POS — produit (1 bouton = 1 article : emoji + couleur).
 */
class Product extends Model
{
    use HasCommercialFields;
    use BelongsToTenant;

    protected $table = 'tagtoa_pos_products';

    protected $fillable = ['tenant_id', 'terminal_id', 'name', 'price', 'cost_price', 'unit', 'low_stock_threshold', 'sku', 'supplier_id', 'tax_rate', 'emoji', 'color', 'image_path', 'stock', 'is_active', 'sort'];

    protected $casts = [
        'price'      => 'decimal:2',
        'cost_price' => 'decimal:2',
        // Quantités décimales : le riz se vend à la mamit, la viande à la livre.
        // 'float' plutôt que 'decimal:3' pour que l'affichage reste « 5 » et
        // non « 5.000 » quand le commerce compte à la pièce.
        'low_stock_threshold' => 'float',
        'stock'               => 'float',
        'is_active'           => 'boolean',
    ];

    /**
     * L'adresse publique de la photo, ou null.
     *
     * Passe par Storage::url plutôt que par un chemin construit à la main : le
     * disque peut changer (local, S3) sans qu'aucune vue n'ait à le savoir.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? \Illuminate\Support\Facades\Storage::url($this->image_path)
            : null;
    }

    /**
     * Caisse de SAISIE — pas le propriétaire.
     *
     * Le catalogue appartient au commerce (`tenant_id`) et toutes ses caisses le
     * partagent. Cette relation ne dit que sur quelle caisse l'article a été
     * créé. Pour chercher un article, passer par PosCatalog, jamais par ici.
     */
    /** Le fournisseur habituel. Nullable : rien ne dépend de sa présence. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\Modules\Tagtoa\App\Models\Inventory\Supplier::class, 'supplier_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }
}
