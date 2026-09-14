<?php

namespace Modules\Tagtoa\App\Support\Catalog;

use Modules\Tagtoa\App\Services\Inventory\StockService;

/**
 * TAGTOA — le volet commercial d'un article, quel que soit son catalogue.
 *
 * Les colonnes (prix d'achat, unité, seuil) existent des deux côtés, parce que
 * la caisse vend les deux catalogues. La LOGIQUE, elle, n'est écrite qu'ici :
 * un plat du menu et un article de la caisse calculent leur marge de la même
 * façon, et une correction profite aux deux.
 */
trait HasCommercialFields
{
    /** Ce qui reste sur une unité vendue. Null = prix d'achat non renseigné. */
    public function getMarginAttribute(): ?float
    {
        return Pricing::margin(
            $this->cost_price === null ? null : (float) $this->cost_price,
            (float) $this->price
        );
    }

    /** Marge en pourcentage du prix de vente. Null si on ne peut rien dire. */
    public function getMarginPercentAttribute(): ?float
    {
        return Pricing::marginPercent(
            $this->cost_price === null ? null : (float) $this->cost_price,
            (float) $this->price
        );
    }

    /** Vendu au-dessous de son prix d'achat. */
    public function isSoldAtLoss(): bool
    {
        return Pricing::isSoldAtLoss(
            $this->cost_price === null ? null : (float) $this->cost_price,
            (float) $this->price
        );
    }

    /** Unité de vente, retombant sur la pièce si elle est inconnue. */
    public function getUnitKeyAttribute(): string
    {
        return Pricing::unit($this->attributes['unit'] ?? null);
    }

    public function getUnitLabelAttribute(): string
    {
        return Pricing::unitLabel($this->attributes['unit'] ?? null);
    }

    /** Se vend-il en quantités fractionnées (2,5 livres de riz) ? */
    public function allowsDecimalQty(): bool
    {
        return Pricing::allowsDecimal($this->attributes['unit'] ?? null);
    }

    /**
     * Faut-il recommander ? Un stock non suivi n'alerte jamais.
     *
     * La règle vit dans StockService — un seul endroit décide, sinon deux
     * écrans finissent par dire l'inverse l'un de l'autre sur le même article.
     */
    public function isLowStock(): bool
    {
        return StockService::isLow(
            $this->stock === null ? null : (float) $this->stock,
            $this->low_stock_threshold === null ? null : (float) $this->low_stock_threshold
        );
    }

    /**
     * Les articles à recommander, en SQL.
     *
     * La même règle que isLowStock(), écrite pour la base : le tableau de bord
     * compte des milliers de lignes, il ne peut pas les charger une par une.
     * COALESCE applique le seuil de l'article quand il existe, le plancher
     * commun sinon — exactement ce que fait StockService en PHP.
     */
    public function scopeLowStock($query)
    {
        return $query->whereNotNull('stock')
            ->whereRaw('stock <= COALESCE(low_stock_threshold, ?)', [StockService::LOW_THRESHOLD]);
    }

    /** Plus rien à vendre. */
    public function isOutOfStock(): bool
    {
        return StockService::isOut($this->stock === null ? null : (float) $this->stock);
    }

    /** Quantité normalisée pour l'unité de CET article. */
    public function normalizeQty(float $qty): float
    {
        return Pricing::normalizeQty($this->attributes['unit'] ?? null, $qty);
    }

    /**
     * Prix affiché avec son unité : « 150 / mamit ».
     * Une pièce n'a pas besoin d'être précisée — personne ne dit « 75 / pièce ».
     */
    public function priceWithUnit(string $currency): string
    {
        $prix = \Modules\Tagtoa\App\Support\Money::format((float) $this->price, $currency);

        return $this->unit_key === Pricing::DEFAULT_UNIT
            ? $prix
            : $prix.' / '.$this->unit_label;
    }
}
