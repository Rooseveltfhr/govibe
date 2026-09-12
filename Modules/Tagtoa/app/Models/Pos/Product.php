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

    protected $fillable = ['tenant_id', 'terminal_id', 'name', 'price', 'cost_price', 'unit', 'low_stock_threshold', 'sku', 'emoji', 'color', 'stock', 'is_active', 'sort'];

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
     * Caisse de SAISIE — pas le propriétaire.
     *
     * Le catalogue appartient au commerce (`tenant_id`) et toutes ses caisses le
     * partagent. Cette relation ne dit que sur quelle caisse l'article a été
     * créé. Pour chercher un article, passer par PosCatalog, jamais par ici.
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }
}
