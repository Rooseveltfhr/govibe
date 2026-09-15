<?php

namespace Modules\Agents\Templates;

use Modules\Agents\DTO\AgentDefinition;

/**
 * Modèl ajan lekòl — enskripsyon, orè, kominikasyon ak paran.
 */
class SchoolTemplate
{
    public const WRITE_TOOLS = ['start_enrollment', 'book_meeting'];

    public const READ_TOOLS = ['get_programs', 'get_fees', 'get_hours', 'get_requirements'];

    private const PROMPT = <<<'PROMPT'
        Ou se asistan yon lekòl. Travay ou se ede paran ak elèv yo: enfòmasyon
        sou enskripsyon, pwogram, frè, ak pran randevou ak direksyon an.

        Kijan pou reponn:
        - Kout, klè, akeyan. Anpil paran ekri apre travay, yo pa gen anpil tan.
        - Nan lang moun nan pale a.

        Sa ou pa janm fè:
        - Envante yon frè, yon dokiman obligatwa oswa yon dat ki pa nan
          enfòmasyon ou genyen.
        - Pran yon desizyon final sou yon enskripsyon — se direksyon an ki
          fè sa. Ou ka kòmanse pwosesis la epi pran yon randevou.
        - Kontinye ap devine lè ou pa konprann — pase bay yon moun.
        PROMPT;

    /** @param array<string, mixed> $knowledge */
    public static function make(
        string $key,
        string $name,
        array $knowledge = [],
        array $channels = ['whatsapp'],
        array $languages = ['ht', 'fr'],
        ?string $handoffTo = null,
    ): AgentDefinition {
        return new AgentDefinition(
            key: $key,
            name: $name,
            sector: 'school',
            systemPrompt: self::PROMPT,
            tools: [...self::READ_TOOLS, ...self::WRITE_TOOLS],
            channels: $channels,
            languages: $languages,
            knowledge: $knowledge,
            confirmation: [
                'always_confirm' => self::WRITE_TOOLS,
                'never_confirm' => self::READ_TOOLS,
                'handoff_below' => 0.50,
            ],
            handoffTo: $handoffTo,
            routingStrategy: 'balanced',
        );
    }

    /** @return list<string> */
    public static function sampleQuestions(string $language = 'ht'): array
    {
        return match ($language) {
            'fr' => [
                "Quels documents faut-il pour l'inscription ?",
                'Combien coûtent les frais scolaires ?',
                'Puis-je prendre un rendez-vous avec la direction ?',
            ],
            'en' => [
                'What documents are needed for enrollment?',
                'How much are the school fees?',
                'Can I book a meeting with the principal?',
            ],
            default => [
                'Ki dokiman ki nesesè pou enskripsyon an?',
                'Konbyen frè lekòl la ye?',
                'Èske m ka pran yon randevou ak direksyon an?',
            ],
        };
    }
}
