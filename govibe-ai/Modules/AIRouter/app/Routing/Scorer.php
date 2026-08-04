<?php

namespace Modules\AIRouter\Routing;

use Modules\AIRouter\DTO\Candidate;
use Modules\AIRouter\DTO\RoutingContext;

/**
 * Cœur du routage : note chaque candidat sur cinq axes — coût, vitesse,
 * qualité, préférences, disponibilité — puis classe par score décroissant.
 *
 * Classe pure (aucune E/S, aucune dépendance framework) : c'est la logique la
 * plus délicate du système, elle doit être testable au jeton près.
 *
 * Chaque axe est normalisé entre 0 et 1 **relativement aux candidats en
 * présence** : un modèle n'est pas « cher » dans l'absolu, il l'est par
 * rapport aux alternatives disponibles à cet instant.
 */
class Scorer
{
    public const AXES = ['cost', 'speed', 'quality', 'preference', 'availability'];

    /**
     * Pondérations par stratégie. « balanced » vient de la configuration
     * (config/govibe.php) pour rester ajustable par plan et par organisation.
     *
     * @var array<string, array<string, float>>
     */
    private const STRATEGY_WEIGHTS = [
        'cost' => ['cost' => 0.60, 'speed' => 0.10, 'quality' => 0.10, 'preference' => 0.10, 'availability' => 0.10],
        'speed' => ['cost' => 0.10, 'speed' => 0.55, 'quality' => 0.15, 'preference' => 0.10, 'availability' => 0.10],
        'quality' => ['cost' => 0.05, 'speed' => 0.10, 'quality' => 0.60, 'preference' => 0.15, 'availability' => 0.10],
    ];

    /** @param array<string, float> $balancedWeights */
    public function __construct(private readonly array $balancedWeights = [])
    {
        //
    }

    /**
     * @param  list<Candidate>  $candidates
     * @return list<Candidate> classés du meilleur au moins bon
     */
    public function rank(array $candidates, RoutingContext $context): array
    {
        if ($candidates === []) {
            return [];
        }

        $weights = $this->weightsFor($context->strategy);

        $costs = array_map(
            fn (Candidate $c): int => $c->model->costMicro($context->estimatedInputTokens, $context->estimatedOutputTokens),
            $candidates,
        );
        $latencies = array_map(fn (Candidate $c): int => $this->latencyOf($c), $candidates);

        $scored = [];

        foreach ($candidates as $index => $candidate) {
            $axes = [
                // Moins cher = meilleur, d'où l'inversion.
                'cost' => 1.0 - $this->normalize($costs[$index], $costs),
                'speed' => 1.0 - $this->normalize($latencies[$index], $latencies),
                'quality' => $candidate->model->qualityFor($context->language) / 100,
                'preference' => $this->preferenceScore($candidate, $context),
                'availability' => $this->availabilityScore($candidate),
            ];

            $score = 0.0;

            foreach ($axes as $axis => $value) {
                $score += ($weights[$axis] ?? 0.0) * $value;
            }

            $scored[] = $candidate->withScore($score, $this->explain($context->strategy, $axes, $score));
        }

        usort($scored, static fn (Candidate $a, Candidate $b): int => $b->score <=> $a->score);

        return $scored;
    }

    /** @return array<string, float> */
    public function weightsFor(string $strategy): array
    {
        if (isset(self::STRATEGY_WEIGHTS[$strategy])) {
            return self::STRATEGY_WEIGHTS[$strategy];
        }

        $defaults = ['cost' => 0.35, 'speed' => 0.20, 'quality' => 0.25, 'preference' => 0.10, 'availability' => 0.10];

        $weights = [];

        foreach (self::AXES as $axis) {
            $weights[$axis] = isset($this->balancedWeights[$axis])
                ? (float) $this->balancedWeights[$axis]
                : $defaults[$axis];
        }

        return $weights;
    }

    /**
     * Latence retenue : la mesure réelle si l'on dispose d'observations,
     * sinon l'estimation du manifeste.
     */
    private function latencyOf(Candidate $candidate): int
    {
        return $candidate->health->sampleCount > 0 && $candidate->health->avgLatencyMs > 0
            ? $candidate->health->avgLatencyMs
            : $candidate->model->expectedLatencyMs;
    }

    private function preferenceScore(Candidate $candidate, RoutingContext $context): float
    {
        if ($context->preferredProviders === []) {
            return 0.5; // neutre : aucune préférence exprimée
        }

        $position = array_search($candidate->providerKey(), $context->preferredProviders, true);

        if ($position === false) {
            return 0.0;
        }

        // Le premier préféré vaut 1.0, les suivants décroissent doucement.
        return 1.0 / (1 + (int) $position);
    }

    private function availabilityScore(Candidate $candidate): float
    {
        if (! $candidate->health->available) {
            return 0.0;
        }

        if ($candidate->health->sampleCount === 0) {
            return 0.8; // pas encore d'observation : optimisme prudent
        }

        return max(0.0, 1.0 - $candidate->health->errorRate);
    }

    /**
     * Normalise une valeur entre le minimum et le maximum observés.
     * Retourne 0 quand tous les candidats sont à égalité (aucun n'est pénalisé).
     *
     * @param  list<int>  $values
     */
    private function normalize(int $value, array $values): float
    {
        $min = min($values);
        $max = max($values);

        if ($max === $min) {
            return 0.0;
        }

        return ($value - $min) / ($max - $min);
    }

    /** @param array<string, float> $axes */
    private function explain(string $strategy, array $axes, float $score): string
    {
        $parts = [];

        foreach ($axes as $axis => $value) {
            $parts[] = sprintf('%s=%.2f', $axis, $value);
        }

        return sprintf('stratégie=%s %s score=%.3f', $strategy, implode(' ', $parts), $score);
    }
}
