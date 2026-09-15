<?php

namespace Modules\Agents\Contracts;

use Modules\Agents\DTO\AgentReply;
use Modules\Agents\DTO\IncomingMessage;

/**
 * Yon kanal: WhatsApp, telefòn (Sonivo), widget web, API.
 *
 * Menm patèn ak konektè LLM yo: ajoute yon kanal = yon klas ki respekte
 * kontra sa a, epi runtime lan pa chanje yon liy. Se sa ki fè ajan vokal la
 * pa yon dezyèm pwodwi — se yon dezyèm kanal.
 */
interface Channel
{
    /** Kle kanal la: 'whatsapp', 'phone', 'web', 'api'. */
    public function key(): string;

    /**
     * Tradui sa kanal la resevwa nan fòm nòmalize a.
     * Retounen null si done a pa yon mesaj (akize resepsyon, eta…).
     *
     * @param  array<string, mixed>  $payload
     */
    public function parse(array $payload): ?IncomingMessage;

    /** Voye repons lan nan fòma kanal la konprann. */
    public function send(IncomingMessage $origin, AgentReply $reply): void;

    /** Èske kanal la ka montre bouton repons rapid? */
    public function supportsQuickReplies(): bool;
}
