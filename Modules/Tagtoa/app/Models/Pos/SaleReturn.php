<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA POS — un retour : un document à part, jamais une vente corrigée.
 *
 * La vente d'origine ne bouge pas. La vérité comptable est la somme des deux —
 * c'est ce qui permet de dire, des mois plus tard, ce qui s'est passé au
 * comptoir, et ce qui empêche d'encaisser puis d'effacer la ligne.
 */
class SaleReturn extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_pos_returns';

    /** Pourquoi la marchandise revient. Ce qui coûte n'est pas la même chose. */
    public const KINDS = [
        'customer'  => 'Le client a changé d\'avis',
        'defective' => 'Article défectueux',
        'error'     => 'Erreur de caisse',
        'expired'   => 'Périmé',
    ];

    protected $fillable = [
        'tenant_id', 'sale_id', 'terminal_id', 'staff_id', 'reference',
        'subtotal', 'tax_total', 'total', 'currency',
        'reason', 'kind', 'restocked', 'idempotency_key', 'returned_at',
    ];

    protected $casts = [
        'subtotal'    => 'decimal:2',
        'tax_total'   => 'decimal:2',
        'total'       => 'decimal:2',
        'restocked'   => 'boolean',
        'returned_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'return_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['customer'];
    }
}
