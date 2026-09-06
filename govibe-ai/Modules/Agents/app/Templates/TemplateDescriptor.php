<?php

namespace Modules\Agents\Templates;

use Modules\Agents\DTO\AgentDefinition;

/**
 * Metadone yon modèl ki nan katalòg la — sa ki ap ranpli yon kat sou paj
 * akèy la (restoran, klinik, lekòl, chatbot sipò…).
 *
 * `kind` fè yon diferans reyèl, se pa yon etikèt: yon **chatbot** reponn
 * kesyon (li gen sèlman zouti lekti), yon **ajan** aji (li ka pran yon
 * kòmand oswa yon randevou, kidonk li gen règ konfimasyon). Paj akèy la
 * gen yon blòk pou chak.
 */
final readonly class TemplateDescriptor
{
    public const KIND_AGENT = 'agent';

    public const KIND_CHATBOT = 'chatbot';

    /**
     * @param  callable(string, string, array<string, mixed>, list<string>, list<string>, ?string): AgentDefinition  $factory
     * @param  callable(string): list<string>  $sampleQuestions
     * @param  list<string>  $capabilities  sa modèl la fè konkrèman, an fraz kout
     * @param  array<string, string>  $knowledgeFields  etikèt => kle (sa fòm kreyasyon an mande)
     */
    public function __construct(
        public string $sector,
        public string $label,
        public string $description,
        public mixed $factory,
        public mixed $sampleQuestions,
        public array $capabilities = [],
        public string $kind = self::KIND_AGENT,
        public array $knowledgeFields = [],
    ) {}

    public function isChatbot(): bool
    {
        return $this->kind === self::KIND_CHATBOT;
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->knowledgeFields !== []
            ? $this->knowledgeFields
            : ['Informations' => 'infos', 'Horaires' => 'horaires', 'Adresse' => 'adresse'];
    }
}
