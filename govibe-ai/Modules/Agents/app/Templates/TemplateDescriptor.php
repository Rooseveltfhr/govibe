<?php

namespace Modules\Agents\Templates;

use Modules\Agents\DTO\AgentDefinition;

/**
 * Metadone yon modèl ajan disponib nan katalòg la — sa ki ap ranpli yon
 * kat nan lis « chwazi yon modèl » (restoran, klinik, lekòl…).
 */
final readonly class TemplateDescriptor
{
    /**
     * @param  callable(string, string, array<string, mixed>, list<string>, list<string>, ?string): AgentDefinition  $factory
     * @param  callable(string): list<string>  $sampleQuestions
     */
    public function __construct(
        public string $sector,
        public string $label,
        public string $description,
        public mixed $factory,
        public mixed $sampleQuestions,
    ) {}
}
