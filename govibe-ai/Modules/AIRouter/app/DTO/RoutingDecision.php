<?php

namespace Modules\AIRouter\DTO;

/**
 * Trace de la décision : quel modèle a servi, après combien de tentatives, et
 * quels candidats ont échoué. Journalisée dans `ai_requests` — c'est ce qui
 * rend le routage auditable au lieu d'être une boîte noire.
 */
final readonly class RoutingDecision
{
    /** @param list<string> $failedProviders */
    public function __construct(
        public string $providerKey,
        public string $modelKey,
        public string $reason,
        public int $attempts = 1,
        public array $failedProviders = [],
    ) {}

    public function didFailover(): bool
    {
        return $this->failedProviders !== [];
    }
}
