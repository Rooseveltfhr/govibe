<?php

namespace Modules\Tagtoa\App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;

/**
 * TAGTOA — un code qui désigne un article dans UN commerce.
 *
 * Code-barres imprimé par le fabricant, référence interne du commerce, ou code
 * fabriqué par TAGTOA pour un produit local qui n'en a pas.
 */
class ProductCode extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_product_codes';

    protected $fillable = [
        'tenant_id', 'source', 'product_id', 'code', 'type', 'label', 'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    /** Référence de l'article visé (« menu:7 »). */
    public function getRefAttribute(): string
    {
        return CatalogRef::make($this->source, (int) $this->product_id);
    }

    /** Format lisible : « EAN-13 », « Code TAGTOA ». */
    public function getTypeLabelAttribute(): string
    {
        return Barcode::TYPE_LABELS[$this->type] ?? 'Inconnu';
    }

    /** Code fabriqué par TAGTOA plutôt qu'imprimé par un fabricant. */
    public function isInternal(): bool
    {
        return $this->type === Barcode::TYPE_INTERNAL;
    }
}
