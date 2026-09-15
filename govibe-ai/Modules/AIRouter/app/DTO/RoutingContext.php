<?php

namespace Modules\AIRouter\DTO;

use Modules\AIProvider\Enums\Capability;

/**
 * Tout ce dont le routeur a besoin pour choisir : la capacité demandée, la
 * contrainte dominante, les préférences du client et la langue détectée.
 */
final readonly class RoutingContext
{
    /**
     * @param  string  $strategy  cost | speed | quality | balanced
     * @param  list<string>  $preferredProviders  clés privilégiées (préférences utilisateur/organisation)
     * @param  list<string>  $blockedProviders  clés interdites (politique de plan ou choix client)
     */
    public function __construct(
        public Capability $capability,
        public string $strategy = 'balanced',
        public ?string $forcedModel = null,
        public array $preferredProviders = [],
        public array $blockedProviders = [],
        public ?string $language = null,
        public int $estimatedInputTokens = 0,
        public int $estimatedOutputTokens = 512,
        public int $maxAttempts = 3,
    ) {}
}
