<?php

namespace Modules\Agents\Runtime;

use Modules\Agents\DTO\AgentDefinition;
use Modules\Agents\DTO\AgentReply;
use Modules\Agents\DTO\IncomingMessage;
use Modules\Agents\DTO\PendingAction;
use Modules\AIServices\Chat\ChatService;

/**
 * Kote yon ajan reyèlman viv.
 *
 * De responsablite, epi yo apa espre:
 *
 *   respond()  — reponn yon mesaj, AK memwa konvèsasyon an.
 *   decide()   — deside sò yon aksyon ki pwopoze, atravè ConfirmationPolicy.
 *
 * `decide()` se sèl kote yon aksyon ka vin yon repons. Lè bouk zouti a rive,
 * li rele isit la — kidonk règ sekirite yo ap deja an plas, yo p ap yon
 * bagay nou sonje ajoute apre.
 */
class AgentRuntime
{
    public function __construct(private readonly ChatService $chat) {}

    /**
     * Reponn yon mesaj epi retounen konvèsasyon an ki grandi.
     *
     * Nou retounen yon nouvo Conversation olye nou modifye youn: konsa yon
     * apèl ki echwe pa kite yon istorik ki kòche dèyè l.
     */
    public function respond(
        AgentDefinition $agent,
        Conversation $conversation,
        IncomingMessage $message,
        ?string $language = null,
    ): TurnOutcome {
        $language ??= $message->language ?? $agent->defaultLanguage();

        $withUser = $conversation->withTurn(
            ConversationTurn::user($message->text, $message->wasSpoken()),
        );

        $startedAt = hrtime(true);

        $outcome = $this->chat->complete(
            messages: $withUser->messagesFor($agent, $language),
            strategy: $agent->routingStrategy,
        );

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $answer = $outcome['response']->content;

        return new TurnOutcome(
            conversation: $withUser->withTurn(ConversationTurn::assistant($answer, [
                'provider' => $outcome['provider'],
                'model' => $outcome['model'],
                'latency_ms' => $latencyMs,
            ])),
            reply: AgentReply::answer($answer),
            provider: $outcome['provider'],
            model: $outcome['model'],
            latencyMs: $latencyMs,
        );
    }

    /**
     * Sò yon aksyon ki pwopoze: fè l, konfime l, oswa pase l bay yon moun.
     *
     * Se politik ajan an ki deside — pa kòd apèlan an. Konsa yon klinik ka
     * pi strik pase yon restoran san yon sèl `if` anplis nan runtime lan.
     */
    public function decide(
        AgentDefinition $agent,
        PendingAction $action,
        bool $spoken = false,
        ?string $language = null,
    ): AgentReply {
        $language ??= $agent->defaultLanguage();
        $policy = ConfirmationPolicy::fromArray($agent->confirmation);

        return match ($policy->decide($action, $spoken)) {
            ConfirmationPolicy::HANDOFF => AgentReply::handoff(
                self::handoffText($agent, $language),
            ),
            ConfirmationPolicy::CONFIRM => AgentReply::confirm(
                $policy->question($action, $language),
                $action,
            ),
            default => AgentReply::acted(self::actedText($action, $language)),
        };
    }

    private static function handoffText(AgentDefinition $agent, string $language): string
    {
        $contact = $agent->handoffTo;

        return match ($language) {
            'fr' => $contact === null
                ? "Je ne suis pas sûr — je préfère vous passer quelqu'un."
                : "Je ne suis pas sûr — contactez {$contact}.",
            'en' => $contact === null
                ? "I'm not sure — let me hand you to someone."
                : "I'm not sure — please contact {$contact}.",
            default => $contact === null
                ? 'Mwen pa sèten — mwen pito pase w bay yon moun.'
                : "Mwen pa sèten — tanpri kontakte {$contact}.",
        };
    }

    private static function actedText(PendingAction $action, string $language): string
    {
        $summary = $action->summary();

        return match ($language) {
            'fr' => $summary === '' ? "C'est fait." : "C'est fait : {$summary}.",
            'en' => $summary === '' ? 'Done.' : "Done: {$summary}.",
            default => $summary === '' ? 'Sa fèt.' : "Sa fèt: {$summary}.",
        };
    }
}
