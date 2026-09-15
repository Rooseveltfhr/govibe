<?php

namespace Modules\Agents\Templates;

use Closure;
use Modules\Agents\DTO\AgentDefinition;

/**
 * Modèl **chatbot**: li reponn kesyon, li pa aji.
 *
 * Diferans ak yon ajan an pa yon non — se zouti yo. Yon chatbot gen
 * SÈLMAN zouti lekti, kidonk pa gen okenn aksyon pou konfime: li pa ka
 * pran yon kòmand, li pa ka pran yon randevou. Se pou sa yon biznis ka
 * mete l an liy pi vit: pi piti risk, pi piti konfigirasyon.
 *
 * Yon sèl klas pou plizyè chatbot: sa ki chanje se misyon an. Ajoute yon
 * chatbot nouvo = yon liy nan AgentTemplateRegistry, pa yon klas nouvo.
 */
class ChatbotTemplate
{
    public const READ_TOOLS = ['get_info', 'get_hours', 'get_prices', 'get_contact'];

    private const BASE = <<<'PROMPT'
        Kijan pou reponn:
        - Kout ak klè. De oswa twa fraz, pa yon paj.
        - Nan lang moun nan ekri a.
        - Sèvi SÈLMAN ak enfòmasyon biznis lan ba ou anba a.

        Sa ou pa janm fè:
        - Envante yon pri, yon orè, yon adrès oswa yon règleman.
        - Pwomèt yon bagay (yon kòmand, yon randevou, yon ranbousman):
          ou pa gen dwa fè aksyon. Di sa, epi pase moun nan bay yon moun.
        - Kontinye ap devine lè ou pa konprann.
        PROMPT;

    /**
     * Bay yon fabrik pou yon chatbot ki gen yon misyon presi.
     *
     * @return Closure(string, string, array<string, mixed>, list<string>, list<string>, ?string): AgentDefinition
     */
    public static function maker(string $sector, string $mission): Closure
    {
        return static function (
            string $key,
            string $name,
            array $knowledge = [],
            array $channels = ['whatsapp'],
            array $languages = ['ht', 'fr'],
            ?string $handoffTo = null,
        ) use ($sector, $mission): AgentDefinition {
            return new AgentDefinition(
                key: $key,
                name: $name,
                sector: $sector,
                systemPrompt: $mission."\n\n".self::BASE,
                tools: self::READ_TOOLS,
                channels: $channels,
                languages: $languages,
                knowledge: $knowledge,
                confirmation: [
                    // Zewo aksyon ekri: pa gen anyen pou konfime. Men sèy
                    // handoff la rete — yon chatbot ki pa sèten dwe pase
                    // moun nan bay yon moun, li pa dwe envante.
                    'always_confirm' => [],
                    'never_confirm' => self::READ_TOOLS,
                    'handoff_below' => 0.50,
                ],
                handoffTo: $handoffTo,
            );
        };
    }

    /** @return list<string> */
    public static function sampleQuestions(string $language = 'ht'): array
    {
        return match ($language) {
            'fr' => [
                'Quels sont vos horaires ?',
                'Où êtes-vous situés ?',
                'Combien coûte le service ?',
                'Comment vous contacter ?',
            ],
            'en' => [
                'What are your opening hours?',
                'Where are you located?',
                'How much does it cost?',
                'How do I reach you?',
            ],
            default => [
                'Ki lè nou louvri?',
                'Kote nou ye?',
                'Konbyen sèvis la koute?',
                'Kijan pou m kontakte nou?',
            ],
        };
    }
}
