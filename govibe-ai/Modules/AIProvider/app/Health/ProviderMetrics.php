<?php

namespace Modules\AIProvider\Health;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\AIProvider\DTO\ProviderHealth;

/**
 * Mesures réelles de latence et d'erreurs sur une fenêtre glissante.
 * Le routeur s'en sert pour noter vitesse et disponibilité : ce sont des
 * observations, jamais des constantes de configuration.
 */
class ProviderMetrics
{
    public function __construct(
        private readonly Cache $cache,
        private readonly int $windowSeconds = 300,
    ) {}

    public function record(string $providerKey, int $latencyMs, bool $succeeded): void
    {
        $window = $this->windowKey();

        $this->increment($this->key($providerKey, $window, 'count'), 1);
        $this->increment($this->key($providerKey, $window, 'latency'), max(0, $latencyMs));

        if (! $succeeded) {
            $this->increment($this->key($providerKey, $window, 'errors'), 1);
        }
    }

    public function health(string $providerKey): ProviderHealth
    {
        $window = $this->windowKey();

        $count = $this->readInt($this->key($providerKey, $window, 'count'));

        if ($count === 0) {
            return ProviderHealth::unknown($providerKey);
        }

        $errors = $this->readInt($this->key($providerKey, $window, 'errors'));
        $latency = $this->readInt($this->key($providerKey, $window, 'latency'));

        return new ProviderHealth(
            providerKey: $providerKey,
            available: true,
            errorRate: $errors / $count,
            avgLatencyMs: (int) round($latency / $count),
            sampleCount: $count,
        );
    }

    private function increment(string $key, int $by): void
    {
        $current = $this->readInt($key);

        // put() plutôt que increment() : compatible avec tous les pilotes de
        // cache, y compris `array` en test.
        $this->cache->put($key, $current + $by, $this->windowSeconds * 2);
    }

    private function readInt(string $key): int
    {
        $value = $this->cache->get($key, 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    private function windowKey(): int
    {
        return intdiv(time(), max(1, $this->windowSeconds));
    }

    private function key(string $providerKey, int $window, string $suffix): string
    {
        return "govibe:metrics:{$providerKey}:{$window}:{$suffix}";
    }
}
