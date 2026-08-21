<?php

namespace Modules\Agents\Runtime;

use Modules\Agents\DTO\AgentDefinition;
use Modules\AIServices\Chat\ChatService;

/**
 * Fè yon ajan reponn yon seri kesyon dirèkteman sou routeur ki deja bati a.
 *
 * Se sa k ap sèvi bouton « Demo » a nan entèfas la: yon machann dwe ka wè
 * ajan l mache — ak vrè modèl, vrè repons — anvan li pibliye l sou WhatsApp
 * oswa telefòn. Zewo mòk, zewo « pretann »: si demo a mache, ajan an mache.
 */
class DemoConversation
{
    public function __construct(private readonly ChatService $chat) {}

    /**
     * @param  list<string>  $questions
     * @return list<DemoTurn>
     */
    public function run(AgentDefinition $agent, array $questions, ?string $language = null): array
    {
        $language ??= $agent->defaultLanguage();
        $systemPrompt = $agent->compiledPrompt($language);

        $turns = [];

        foreach ($questions as $question) {
            $startedAt = hrtime(true);

            $outcome = $this->chat->complete(
                messages: [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question],
                ],
                strategy: $agent->routingStrategy,
            );

            $turns[] = new DemoTurn(
                question: $question,
                answer: $outcome['response']->content,
                provider: $outcome['provider'],
                model: $outcome['model'],
                latencyMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            );
        }

        return $turns;
    }
}
