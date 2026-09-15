<?php

namespace Modules\AIProvider\Support;

/**
 * Logique pure du disjoncteur (aucune dépendance framework, donc testable
 * en unitaire strict). L'état persistant vit dans Redis ; cette classe ne
 * fait que décider des transitions.
 *
 * fermé → (seuil d'échecs atteint) → ouvert → (après cooldown) → semi-ouvert
 * semi-ouvert → succès → fermé | échec → ouvert
 */
final class CircuitBreakerState
{
    public const CLOSED = 'closed';

    public const OPEN = 'open';

    public const HALF_OPEN = 'half_open';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60,
    ) {}

    /**
     * État effectif à l'instant `now`, en tenant compte du cooldown écoulé.
     */
    public function currentState(int $consecutiveFailures, ?int $openedAt, int $now): string
    {
        if ($consecutiveFailures < $this->failureThreshold) {
            return self::CLOSED;
        }

        if ($openedAt === null) {
            return self::OPEN;
        }

        return ($now - $openedAt) >= $this->cooldownSeconds ? self::HALF_OPEN : self::OPEN;
    }

    /** Le fournisseur peut-il recevoir du trafic ? */
    public function allowsTraffic(int $consecutiveFailures, ?int $openedAt, int $now): bool
    {
        return $this->currentState($consecutiveFailures, $openedAt, $now) !== self::OPEN;
    }

    /**
     * Nouvel état après un échec.
     *
     * @return array{failures: int, opened_at: int|null}
     */
    public function recordFailure(int $consecutiveFailures, ?int $openedAt, int $now): array
    {
        $failures = $consecutiveFailures + 1;

        if ($failures < $this->failureThreshold) {
            return ['failures' => $failures, 'opened_at' => null];
        }

        // Franchissement du seuil, ou nouvel échec en semi-ouvert : (ré)ouverture.
        $wasHalfOpen = $this->currentState($consecutiveFailures, $openedAt, $now) === self::HALF_OPEN;

        return [
            'failures' => $failures,
            'opened_at' => ($openedAt === null || $wasHalfOpen) ? $now : $openedAt,
        ];
    }

    /** @return array{failures: int, opened_at: int|null} */
    public function recordSuccess(): array
    {
        return ['failures' => 0, 'opened_at' => null];
    }
}
