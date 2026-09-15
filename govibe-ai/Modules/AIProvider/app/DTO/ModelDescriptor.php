<?php

namespace Modules\AIProvider\DTO;

use Modules\AIProvider\Enums\Capability;

/**
 * Description d'un modèle telle que déclarée par le manifeste d'un connecteur.
 * Les prix sont en **micro-USD par million de jetons** afin de rester en
 * entiers (aucun flottant dans les calculs monétaires).
 */
final readonly class ModelDescriptor
{
    /**
     * @param  list<Capability>  $capabilities
     * @param  array<string, int>  $qualityByLanguage  note 0..100 par langue (ex. ['ht' => 60])
     */
    public function __construct(
        public string $key,
        public string $providerKey,
        public array $capabilities,
        public int $inputPricePerMillion,
        public int $outputPricePerMillion,
        public int $contextWindow = 8192,
        public int $quality = 50,
        public array $qualityByLanguage = [],
        public int $expectedLatencyMs = 2000,
        public ?string $name = null,
    ) {}

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /** Identifiant public exposé par l'API : « openai/gpt-4o-mini ». */
    public function publicId(): string
    {
        return $this->providerKey.'/'.$this->key;
    }

    /**
     * Qualité pour une langue donnée : la note spécifique si elle existe,
     * sinon la note générale du modèle.
     */
    public function qualityFor(?string $language): int
    {
        if ($language !== null && isset($this->qualityByLanguage[$language])) {
            return $this->qualityByLanguage[$language];
        }

        return $this->quality;
    }

    /** Coût en micro-USD pour un volume de jetons donné. */
    public function costMicro(int $inputTokens, int $outputTokens): int
    {
        return (int) round(
            ($inputTokens * $this->inputPricePerMillion + $outputTokens * $this->outputPricePerMillion) / 1_000_000
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromManifest(string $providerKey, array $data): self
    {
        /** @var list<Capability> $capabilities */
        $capabilities = array_map(
            static fn (string $c): Capability => Capability::from($c),
            array_values(array_filter((array) ($data['capabilities'] ?? ['chat']), 'is_string')),
        );

        /** @var array<string, int> $qualityByLanguage */
        $qualityByLanguage = array_map('intval', (array) ($data['quality_by_language'] ?? []));

        return new self(
            key: (string) $data['key'],
            providerKey: $providerKey,
            capabilities: $capabilities,
            inputPricePerMillion: (int) ($data['input_price_per_million'] ?? 0),
            outputPricePerMillion: (int) ($data['output_price_per_million'] ?? 0),
            contextWindow: (int) ($data['context_window'] ?? 8192),
            quality: (int) ($data['quality'] ?? 50),
            qualityByLanguage: $qualityByLanguage,
            expectedLatencyMs: (int) ($data['expected_latency_ms'] ?? 2000),
            name: isset($data['name']) && is_string($data['name']) ? $data['name'] : null,
        );
    }
}
