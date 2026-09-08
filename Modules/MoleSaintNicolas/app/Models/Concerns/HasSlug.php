<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Génère un slug à partir de `name` (ou `title`, pour les modèles qui n'ont
 * pas de colonne `name`, ex. HistoricalEvent) à la création si aucun n'est
 * fourni. Ne re-génère jamais un slug existant (les URLs publiques ne
 * doivent pas bouger).
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            $source = $model->name ?? $model->title ?? null;

            if (empty($model->slug) && ! empty($source)) {
                $model->slug = Str::slug($source);
            }
        });
    }
}
