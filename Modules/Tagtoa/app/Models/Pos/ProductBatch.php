<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA POS — un lot reçu, avec sa propre date de péremption.
 *
 * Deux réassorts du même médicament ont presque toujours deux péremptions
 * différentes. `purchased_at` sur `Product` (une seule date, globale à
 * l'article) ne permettait pas de les distinguer.
 *
 * Une couche de TRAÇABILITÉ, pas un second stock : le compteur qui compte
 * vraiment reste `Product::stock`, tenu par StockLedger.
 */
class ProductBatch extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_pos_product_batches';

    protected $fillable = ['tenant_id', 'product_id', 'quantity', 'expires_at', 'received_at', 'note'];

    protected $casts = [
        'quantity'    => 'float',
        'expires_at'  => 'date',
        'received_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** Combien de jours avant péremption, négatif si déjà périmé. Null si pas de date. */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->expires_at ? now()->startOfDay()->diffInDays($this->expires_at, false) : null;
    }
}
