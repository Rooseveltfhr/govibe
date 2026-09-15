<?php

namespace Modules\AIProvider\DTO;

/**
 * Requête de conversation normalisée. `model` est le modèle **résolu** par le
 * routeur (identifiant côté fournisseur) ; la préférence exprimée par le
 * client vit dans RoutingContext, pas ici.
 */
final readonly class ChatRequest
{
    /** @param list<ChatMessage> $messages */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public bool $stream = false,
        public ?string $language = null,
    ) {}

    public function withModel(string $model): self
    {
        return new self(
            $this->messages,
            $model,
            $this->temperature,
            $this->maxTokens,
            $this->stream,
            $this->language,
        );
    }

    /** @return list<array{role: string, content: string}> */
    public function messagesToArray(): array
    {
        return array_map(fn (ChatMessage $m): array => $m->toArray(), $this->messages);
    }

    /**
     * Estimation grossière du nombre de jetons d'entrée, utilisée avant l'appel
     * pour la réservation de crédits (Phase 2) et le scoring du coût.
     * Approximation volontairement prudente : ~4 caractères par jeton.
     */
    public function estimatedInputTokens(): int
    {
        $characters = 0;

        foreach ($this->messages as $message) {
            $characters += mb_strlen($message->content) + 8; // + surcharge de rôle
        }

        return (int) max(1, ceil($characters / 4));
    }
}
