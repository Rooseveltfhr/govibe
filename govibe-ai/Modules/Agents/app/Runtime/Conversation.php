<?php

namespace Modules\Agents\Runtime;

use Modules\Agents\DTO\AgentDefinition;

/**
 * Memwa yon konvèsasyon.
 *
 * San sa yon ajan pa ka pran yon kòmand. Yon kòmand se pa yon kesyon, se
 * yon echanj:
 *
 *   — Mwen vle de griyo
 *   — Ak ki akonpayman?
 *   — Diri kole
 *
 * Nan twazyèm mesaj la ajan an dwe toujou konnen se de griyo. Yon ajan ki
 * bliye chak tou ka reponn yon kesyon; li pa ka sèvi yon kliyan.
 *
 * Objè a IMIYAB: chak tou bay yon nouvo konvèsasyon. Konsa yon bug pa ka
 * modifye yon istorik ki deja sove, epi li senp pou sere nan yon sesyon.
 */
final readonly class Conversation
{
    /**
     * Konbyen mesaj nou kenbe. Chak mesaj koute jeton nan chak apèl, epi yon
     * kòmand restoran raman depase kèk echanj. Nou koupe pi ansyen yo — se
     * dènye yo ki pote sa kliyan an ap mande kounye a.
     */
    public const DEFAULT_MAX_MESSAGES = 20;

    /** @param list<ConversationTurn> $turns */
    public function __construct(public array $turns = []) {}

    public function withTurn(ConversationTurn $turn): self
    {
        return new self([...$this->turns, $turn]);
    }

    public function isEmpty(): bool
    {
        return $this->turns === [];
    }

    public function count(): int
    {
        return count($this->turns);
    }

    /** Efase tout bagay — bouton « Rekòmanse » a. */
    public function cleared(): self
    {
        return new self;
    }

    /**
     * Mesaj yo jan founisè a tann yo.
     *
     * Konsiy sistèm nan toujou premye epi li pa janm koupe: se li ki pote
     * règ sekirite yo. Se sèlman istorik la ki koupe.
     *
     * @return list<array{role: string, content: string}>
     */
    public function messagesFor(
        AgentDefinition $agent,
        string $language,
        int $maxMessages = self::DEFAULT_MAX_MESSAGES,
    ): array {
        $kept = $maxMessages > 0 && count($this->turns) > $maxMessages
            ? array_slice($this->turns, -$maxMessages)
            : $this->turns;

        $messages = [['role' => 'system', 'content' => $agent->compiledPrompt($language)]];

        foreach ($kept as $turn) {
            $messages[] = ['role' => $turn->role, 'content' => $turn->content];
        }

        return $messages;
    }

    /** @return list<array{role: string, content: string, meta: array<string, mixed>}> */
    public function toArray(): array
    {
        return array_map(static fn (ConversationTurn $t): array => $t->toArray(), $this->turns);
    }

    /** @param array<int, mixed> $data */
    public static function fromArray(array $data): self
    {
        $turns = [];

        foreach ($data as $row) {
            if (is_array($row)) {
                $turns[] = ConversationTurn::fromArray($row);
            }
        }

        return new self($turns);
    }
}
