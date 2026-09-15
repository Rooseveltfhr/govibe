<?php

namespace Modules\AIRouter\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Émis après chaque appel IA — succès comme échec.
 *
 * C'est le point de jonction entre les modules : Usage journalise, Billing
 * débitera les crédits (Phase 2), Analytics agrégera (Phase 3). Aucun de ces
 * modules n'est appelé directement par le routeur, ce qui garde les
 * dépendances descendantes.
 *
 * La charge utile est volontairement scalaire : les abonnés n'ont pas besoin
 * de connaître les DTO de la couche fournisseurs.
 */
class AIRequestCompleted
{
    use Dispatchable;

    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $uuid,
        public readonly string $capability,
        public readonly string $providerKey,
        public readonly string $modelKey,
        public readonly string $status,
        public readonly int $latencyMs = 0,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $costMicro = 0,
        public readonly int $attempts = 1,
        public readonly bool $streamed = false,
        public readonly ?string $language = null,
        public readonly ?string $routedReason = null,
        public readonly ?string $errorCode = null,
        public readonly array $meta = [],
    ) {}
}
