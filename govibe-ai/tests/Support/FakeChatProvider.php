<?php

namespace Tests\Support;

use Generator;
use Modules\AIProvider\Contracts\AIProvider;
use Modules\AIProvider\Contracts\SupportsChat;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\DTO\StreamChunk;
use Modules\AIProvider\DTO\TokenUsage;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Exceptions\ProviderException;

/**
 * Fournisseur factice : permet d'exercer le routeur et l'API de bout en bout
 * sans aucun appel réseau.
 */
class FakeChatProvider implements AIProvider, SupportsChat
{
    public int $calls = 0;

    /** @param list<string> $deltas */
    public function __construct(
        private readonly string $key,
        private readonly int $inputPricePerMillion = 1_000_000,
        private readonly int $quality = 50,
        private readonly ?ProviderException $failWith = null,
        private readonly string $reply = 'Bonjou!',
        private readonly array $deltas = ['Bon', 'jou', '!'],
        private readonly bool $configured = true,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return ucfirst($this->key);
    }

    /** @return list<Capability> */
    public function capabilities(): array
    {
        return [Capability::Chat];
    }

    /** @return list<ModelDescriptor> */
    public function models(): array
    {
        return [new ModelDescriptor(
            key: $this->key.'-model',
            providerKey: $this->key,
            capabilities: [Capability::Chat],
            inputPricePerMillion: $this->inputPricePerMillion,
            outputPricePerMillion: $this->inputPricePerMillion * 2,
            contextWindow: 128_000,
            quality: $this->quality,
            qualityByLanguage: ['ht' => $this->quality],
            expectedLatencyMs: 1000,
        )];
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $this->calls++;

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return new ChatResponse(
            content: $this->reply,
            model: $request->model ?? $this->key.'-model',
            providerKey: $this->key,
            usage: new TokenUsage(12, 8),
        );
    }

    public function streamChat(ChatRequest $request): Generator
    {
        $this->calls++;

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        foreach ($this->deltas as $delta) {
            yield new StreamChunk($delta);
        }

        yield StreamChunk::end(new TokenUsage(12, 8));
    }
}
