<?php

namespace Modules\Agents\Templates;

use Modules\Agents\DTO\AgentDefinition;

/**
 * Modèl ajan klinik/santé — pran randevou, di sa yo ofri, pale sou pri.
 *
 * Menm règ ak restoran an: reponn kout, pa envante pri, pase bay yon moun
 * lè li pa sèten. Men isit la gen yon liy wouj anplis: yon ajan pa dwe
 * janm bay konsèy medikal — se sa ki fè diferans ant itil ak danjere.
 */
class ClinicTemplate
{
    public const WRITE_TOOLS = ['book_appointment', 'cancel_appointment', 'reschedule_appointment'];

    public const READ_TOOLS = ['get_services', 'get_price', 'get_hours', 'check_availability'];

    private const PROMPT = <<<'PROMPT'
        Ou se asistan yon klinik oswa yon sant sante. Travay ou se ede pasyan
        yo: pran randevou, di ki sèvis klinik la ofri, bay orè ak pri.

        Kijan pou reponn:
        - Kout, klè, respektye. Anpil moun k ap ekri ka enkyete.
        - Nan lang moun nan pale a.
        - Konfime dat ak lè anvan ou anrejistre yon randevou.

        Sa ou pa janm fè:
        - Bay yon dyagnostik oswa yon konsèy medikal, menm si moun nan mande l.
          Di: "Sa a se yon kesyon pou doktè a, mwen ka pran yon randevou pou ou."
        - Envante yon pri, yon sèvis oswa yon orè ki pa nan enfòmasyon ou genyen.
        - Bay okenn enfòmasyon sou yon lòt pasyan.
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
            sector: 'clinic',
            systemPrompt: self::PROMPT,
            tools: [...self::READ_TOOLS, ...self::WRITE_TOOLS],
            channels: $channels,
            languages: $languages,
            knowledge: $knowledge,
            confirmation: [
                'always_confirm' => self::WRITE_TOOLS,
                'never_confirm' => self::READ_TOOLS,
                // Domèn sansib: yon ajan ki pa sèten pase bay yon moun pi vit
                // pase nan restoran an.
                'handoff_below' => 0.60,
            ],
            handoffTo: $handoffTo,
            routingStrategy: 'quality',
        );
    }

    /** @return list<string> */
    public static function sampleQuestions(string $language = 'ht'): array
    {
        return match ($language) {
            'fr' => [
                'Bonjour, avez-vous un rendez-vous disponible cette semaine ?',
                'Combien coûte une consultation générale ?',
                'Quels sont vos horaires le samedi ?',
            ],
            'en' => [
                'Do you have an appointment available this week?',
                'How much does a general consultation cost?',
                'What are your hours on Saturday?',
            ],
            default => [
                'Bonjou, èske gen yon randevou disponib semèn sa a?',
                'Konbyen yon konsiltasyon jeneral koute?',
                'Ki lè nou louvri jou samdi?',
            ],
        };
    }
}
