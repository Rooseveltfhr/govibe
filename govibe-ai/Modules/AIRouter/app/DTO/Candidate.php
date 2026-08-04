<?php

namespace Modules\AIRouter\DTO;

use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\DTO\ProviderHealth;

/**
 * Modèle candidat accompagné de la santé observée de son fournisseur.
 */
final readonly class Candidate
{
    public function __construct(
        public ModelDescriptor $model,
        public ProviderHealth $health,
        public float $score = 0.0,
        public string $reason = '',
    ) {}

    public function withScore(float $score, string $reason): self
    {
        return new self($this->model, $this->health, $score, $reason);
    }

    public function providerKey(): string
    {
        return $this->model->providerKey;
    }
}
