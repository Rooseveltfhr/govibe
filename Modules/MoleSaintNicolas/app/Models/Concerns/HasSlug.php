<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Génère un slug à partir de `name` à la création, si aucun n'est fourni.
 * Ne re-génère jamais un slug existant (les URLs publiques ne doivent pas bouger).
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
}
