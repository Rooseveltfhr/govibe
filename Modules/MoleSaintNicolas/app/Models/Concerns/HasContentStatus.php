<?php

namespace App\Models\Concerns;

use App\Models\User;
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

    /**
     * Ajoute verified_by/verified_at aux données à sauvegarder si ce passage
     * marque le contenu "vérifié" pour la première fois — jamais l'inverse
     * (repasser en needs_review n'efface pas qui avait vérifié auparavant).
     */
    public function applyVerificationStamp(array $data, User $actor): array
    {
        if (($data['content_status'] ?? null) === 'verified' && ! $this->isVerified()) {
            $data['verified_by'] = $actor->id;
            $data['verified_at'] = now();
        }

        return $data;
    }
}
