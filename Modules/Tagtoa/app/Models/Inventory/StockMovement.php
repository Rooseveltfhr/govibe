<?php

namespace Modules\Tagtoa\App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Inventory\MovementType;

/**
 * TAGTOA — une ligne du journal de stock. Écrite une fois, jamais modifiée.
 *
 * Se tromper se corrige par un NOUVEAU mouvement. Réécrire une ligne
 * effacerait la trace de l'erreur, c'est-à-dire exactement ce qu'on cherche à
 * pouvoir retrouver.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_stock_movements';

    /** Journal : une date de création, aucune date de modification. */
    public const UPDATED_AT = null;

    /**
     * Catalogues suivis.
     *
     * Plus large que CatalogRef, qui ne décrit que ce que la CAISSE peut
     * vendre : la boutique en ligne tient son propre catalogue, et son stock
     * mérite la même traçabilité. Ne pas remplacer par CatalogRef — sa
     * normalisation renverrait « store » vers « pos » et rattacherait le
     * mouvement au mauvais article.
     */
    public const SOURCE_MENU  = 'menu';
    public const SOURCE_POS   = 'pos';
    public const SOURCE_STORE = 'store';

    public const SOURCES = [self::SOURCE_MENU, self::SOURCE_POS, self::SOURCE_STORE];

    protected $fillable = [
        'tenant_id', 'source', 'product_id', 'product_name', 'type',
        'delta', 'stock_before', 'stock_after', 'unit_cost', 'supplier_id',
        'staff_id', 'actor_name', 'origin_type', 'origin_id', 'reason',
    ];

    protected $casts = [
        'delta'        => 'float',
        'stock_before' => 'float',
        'stock_after'  => 'float',
        'unit_cost'    => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** Référence de l'article : « menu:7 », « pos:7 », « store:7 ». */
    public function getRefAttribute(): string
    {
        return $this->source.':'.(int) $this->product_id;
    }

    public function getTypeLabelAttribute(): string
    {
        return MovementType::label($this->type);
    }

    /** « +12 » / « -3 » : le signe se lit d'un coup d'œil. */
    public function getDeltaLabelAttribute(): string
    {
        $n = (float) $this->delta;
        $texte = rtrim(rtrim(number_format(abs($n), 3, '.', ''), '0'), '.');

        return ($n < 0 ? '-' : '+').$texte;
    }

    /** Valeur d'une réception : ce que cette entrée a coûté. */
    public function getValueAttribute(): ?float
    {
        return $this->unit_cost === null ? null : round((float) $this->unit_cost * abs((float) $this->delta), 2);
    }
}
