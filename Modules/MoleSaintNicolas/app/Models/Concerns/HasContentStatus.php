<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Statut de vérification du contenu (brief §27) : verified / submitted / needs_review.
 * Rien n'est affiché publiquement comme "vérifié" tant qu'un humain (verified_by) ne l'a pas confirmé.
 */
trait HasContentStatus
{
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('content_status', 'verified');
    }

    public function isVerified(): bool
    {
        return $this->content_status === 'verified';
    }
}
