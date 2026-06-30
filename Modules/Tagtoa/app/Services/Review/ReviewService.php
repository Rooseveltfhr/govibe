<?php

namespace Modules\Tagtoa\App\Services\Review;

use Modules\Tagtoa\App\Models\Review\Review;

/**
 * TAGTOA REVIEWS — capture & agrégation des avis.
 *
 * Sécurité : note bornée 1..5 côté serveur, avis créés en statut "pending"
 * (modération marchand obligatoire avant publication). Idempotent via client_uuid.
 */
class ReviewService
{
    /** Moyenne pondérée bornée, arrondie à 1 décimale. LOGIQUE PURE (testable). */
    public static function average(array $ratings): float
    {
        $vals = [];
        foreach ($ratings as $r) {
            $n = (int) $r;
            if ($n >= 1 && $n <= 5) {
                $vals[] = $n;
            }
        }
        if (! $vals) {
            return 0.0;
        }

        return round(array_sum($vals) / count($vals), 1);
    }

    /** Borne une note utilisateur dans 1..5. LOGIQUE PURE (testable). */
    public static function clampRating($value): int
    {
        return max(1, min(5, (int) $value));
    }

    /** Répartition des notes 5→1. LOGIQUE PURE (testable). */
    public static function distribution(array $ratings): array
    {
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($ratings as $r) {
            $n = (int) $r;
            if (isset($dist[$n])) {
                $dist[$n]++;
            }
        }

        return $dist;
    }

    public function submit(string $type, int $subjectId, ?string $tenantId, ?string $alias, array $payload): Review
    {
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = Review::where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        return Review::create([
            'tenant_id'     => $tenantId,
            'subject_type'  => $type,
            'subject_id'    => $subjectId,
            'subject_alias' => $alias,
            'rating'        => self::clampRating($payload['rating'] ?? 0),
            'author_name'   => $payload['author_name'] ?? '',
            'author_phone'  => $payload['author_phone'] ?? null,
            'author_email'  => $payload['author_email'] ?? null,
            'comment'       => $payload['comment'] ?? null,
            'status'        => 'pending',
            'client_uuid'   => $uuid,
        ]);
    }

    /** Résumé des avis publiés d'une ressource (count, moyenne, répartition). */
    public function summary(string $type, int $subjectId): array
    {
        $ratings = Review::query()->forSubject($type, $subjectId)->approved()
            ->pluck('rating')->all();

        return [
            'count'        => count($ratings),
            'average'      => self::average($ratings),
            'distribution' => self::distribution($ratings),
        ];
    }
}
