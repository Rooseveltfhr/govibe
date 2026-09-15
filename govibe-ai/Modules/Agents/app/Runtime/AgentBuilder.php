<?php

namespace Modules\Agents\Runtime;

use InvalidArgumentException;
use Modules\Agents\DTO\AgentDefinition;
use Modules\Agents\DTO\PendingAction;
use Modules\Agents\Templates\AgentTemplateRegistry;
use Modules\Agents\Templates\TemplateDescriptor;

/**
 * Sèl pòt antre pou konstwi ak teste yon ajan — sa ki dèyè bouton
 * « Kreye » ak « Demo » nan entèfas la.
 *
 * Yon machann pa bezwen konnen ki modèl, ki router, ki politik konfimasyon
 * k ap travay dèyè: li chwazi yon sektè, li ranpli konesans biznis li,
 * epi klas sa a bay li tounen yon ajan pare + yon fason pou teste l anvan
 * li pibliye l sou WhatsApp oswa telefòn.
 */
class AgentBuilder
{
    public function __construct(
        private readonly AgentTemplateRegistry $templates,
        private readonly DemoConversation $demo,
    ) {}

    /** @return list<TemplateDescriptor> */
    public function availableSectors(): array
    {
        return $this->templates->all();
    }

    /**
     * @param  array<string, mixed>  $knowledge
     * @param  list<string>  $channels
     * @param  list<string>  $languages
     *
     * @throws InvalidArgumentException si sektè a pa nan katalòg la
     */
    public function create(
        string $sector,
        string $key,
        string $name,
        array $knowledge = [],
        array $channels = ['whatsapp'],
        array $languages = ['ht', 'fr'],
        ?string $handoffTo = null,
    ): AgentDefinition {
        return $this->templates->make($sector, $key, $name, $knowledge, $channels, $languages, $handoffTo);
    }

    /** Politik konfimasyon ajan an, jan sektè a konfigire l. */
    public function confirmationPolicy(AgentDefinition $agent): ConfirmationPolicy
    {
        return ConfirmationPolicy::fromArray($agent->confirmation);
    }

    /** Deside si yon aksyon dwe fèt dirèk, konfime, oswa pase bay yon moun. */
    public function decide(AgentDefinition $agent, PendingAction $action, bool $spoken = false): string
    {
        return $this->confirmationPolicy($agent)->decide($action, $spoken);
    }

    /**
     * Fè ajan an reponn kesyon egzanp sektè a (oswa kesyon pèsonalize) ak
     * vrè router la — se sa bouton « Demo » a montre anvan piblikasyon.
     *
     * @param  ?list<string>  $questions  null = itilize kesyon egzanp sektè a
     * @return list<DemoTurn>
     */
    public function demo(AgentDefinition $agent, ?array $questions = null, ?string $language = null): array
    {
        $language ??= $agent->defaultLanguage();
        $questions ??= $this->templates->sampleQuestions($agent->sector, $language);

        return $this->demo->run($agent, $questions, $language);
    }
}
