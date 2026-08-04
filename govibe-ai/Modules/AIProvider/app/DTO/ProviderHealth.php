<?php

namespace Modules\AIProvider\DTO;

/**
 * Santé observée d'un fournisseur sur la fenêtre glissante courante.
 * Alimentée par les mesures réelles, jamais par des constantes.
 */
final readonly class ProviderHealth
{
    public function __construct(
        public string $providerKey,
        public bool $available = true,
        public float $errorRate = 0.0,
        public int $avgLatencyMs = 0,
        public int $sampleCount = 0,
    ) {}

    public static function unknown(string $providerKey): self
    {
        return new self($providerKey);
    }
}
