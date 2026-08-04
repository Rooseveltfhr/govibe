<?php

namespace Modules\AIProvider\Health;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\AIProvider\Support\CircuitBreakerState;

/**
 * Persistance du disjoncteur (Redis en production, array en test).
 * Toute la logique de transition vit dans CircuitBreakerState — ici, seul
 * l'entreposage.
 */
class CircuitBreaker
{
    public function __construct(
        private readonly Cache $cache,
        private readonly CircuitBreakerState $state,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function allows(string $providerKey): bool
    {
        [$failures, $openedAt] = $this->read($providerKey);

        return $this->state->allowsTraffic($failures, $openedAt, time());
    }

    public function status(string $providerKey): string
    {
        [$failures, $openedAt] = $this->read($providerKey);

        return $this->state->currentState($failures, $openedAt, time());
    }

    public function recordSuccess(string $providerKey): void
    {
        $next = $this->state->recordSuccess();

        $this->write($providerKey, $next['failures'], $next['opened_at']);
    }

    public function recordFailure(string $providerKey): void
    {
        [$failures, $openedAt] = $this->read($providerKey);

        $next = $this->state->recordFailure($failures, $openedAt, time());

        $this->write($providerKey, $next['failures'], $next['opened_at']);
    }

    /** @return array{0: int, 1: int|null} */
    private function read(string $providerKey): array
    {
        $failures = $this->cache->get($this->key($providerKey, 'failures'), 0);
        $openedAt = $this->cache->get($this->key($providerKey, 'opened_at'));

        return [is_numeric($failures) ? (int) $failures : 0, is_numeric($openedAt) ? (int) $openedAt : null];
    }

    private function write(string $providerKey, int $failures, ?int $openedAt): void
    {
        $this->cache->put($this->key($providerKey, 'failures'), $failures, $this->ttlSeconds);

        if ($openedAt === null) {
            $this->cache->forget($this->key($providerKey, 'opened_at'));

            return;
        }

        $this->cache->put($this->key($providerKey, 'opened_at'), $openedAt, $this->ttlSeconds);
    }

    private function key(string $providerKey, string $suffix): string
    {
        return "govibe:cb:{$providerKey}:{$suffix}";
    }
}
