<?php

namespace Modules\Tagtoa\App\Services\Inventory;

use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\ProductBatch;
use Modules\Tagtoa\App\Support\Inventory\MovementType;

/**
 * TAGTOA POS — recevoir un lot, avec sa péremption.
 *
 * Une pharmacie qui réceptionne 200 comprimés périmant en mars, puis 100
 * autres périmant en juin, doit pouvoir les distinguer — sinon elle vend
 * au hasard et découvre le lot de mars dans la poubelle, pas sur le
 * comptoir. Le stock lui-même continue de passer par StockLedger,
 * inchangé : le lot est une couche de traçabilité EN PLUS.
 */
class BatchService
{
    /**
     * Enregistre un lot reçu et fait monter le stock du produit d'autant.
     *
     * Une seule écriture couvre les deux : sans elle, un lot enregistré mais
     * jamais reflété au stock ferait croire à un réassort qui n'a pas eu
     * lieu, et une caisse continuerait de refuser des ventes pourtant
     * possibles.
     */
    public function receive(Product $product, float $quantity, array $attrs = []): ProductBatch
    {
        $batch = ProductBatch::create([
            'tenant_id'   => $product->tenant_id,
            'product_id'  => $product->id,
            'quantity'    => $quantity,
            'expires_at'  => $attrs['expires_at'] ?? null,
            'received_at' => $attrs['received_at'] ?? now()->toDateString(),
            'note'        => $attrs['note'] ?? null,
        ]);

        app(StockLedger::class)->add($product, $quantity, MovementType::PURCHASE, [
            'reason' => __('Lot reçu').($batch->expires_at ? ' — '.__('périme le :date', ['date' => $batch->expires_at->format('d/m/Y')]) : ''),
        ]);

        return $batch;
    }

    /**
     * Les lots qui périment dans les $jours à venir, ou déjà périmés — le
     * plus proche d'abord. Tenant courant uniquement (BelongsToTenant).
     */
    public function expiringWithin(int $jours = 30): Collection
    {
        return ProductBatch::whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($jours)->toDateString())
            ->with('product:id,name')
            ->orderBy('expires_at')
            ->get();
    }

    /** Tous les lots du commerce, les plus récents d'abord. */
    public function all(): Collection
    {
        return ProductBatch::with('product:id,name')
            ->orderByDesc('received_at')->orderByDesc('id')
            ->get();
    }
}
