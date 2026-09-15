<?php

namespace Modules\Agents\Runtime;

use Modules\Agents\DTO\AgentReply;

/**
 * Rezilta yon tou: repons lan, ak konvèsasyon an ki grandi.
 *
 * Apèlan an dwe sere `conversation` la — se li ki pote memwa a pou pwochen
 * tou a. Yon apèlan ki jete l ap gen yon ajan ki bliye.
 */
final readonly class TurnOutcome
{
    public function __construct(
        public Conversation $conversation,
        public AgentReply $reply,
        public string $provider,
        public string $model,
        public int $latencyMs,
    ) {}
}
