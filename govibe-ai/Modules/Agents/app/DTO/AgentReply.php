<?php

namespace Modules\Agents\DTO;

/**
 * Repons ajan an, nan yon fòm chak kanal ka rann jan li vle.
 *
 * `choices` se bouton rapid sou WhatsApp, opsyon nan yon meni telefonik,
 * oswa bouton sou yon widget — menm done, twa rannman.
 */
final readonly class AgentReply
{
    public const ANSWER = 'answer';        // repons dirèk

    public const CONFIRM = 'confirm';      // mande konfimasyon anvan aji

    public const ACTED = 'acted';          // aksyon an fèt

    public const HANDOFF = 'handoff';      // pase bay yon moun

    public const REFUSED = 'refused';      // deyò domèn ajan an

    /**
     * @param  list<string>  $choices  repons rapid pou itilizatè a
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $text,
        public array $choices = [],
        public ?PendingAction $pendingAction = null,
        public array $meta = [],
    ) {}

    public static function answer(string $text): self
    {
        return new self(self::ANSWER, $text);
    }

    /** @param list<string> $choices */
    public static function confirm(string $text, PendingAction $action, array $choices = []): self
    {
        return new self(self::CONFIRM, $text, $choices !== [] ? $choices : ['Wi, se sa', 'Non, korije'], $action);
    }

    public static function acted(string $text): self
    {
        return new self(self::ACTED, $text);
    }

    public static function handoff(string $text): self
    {
        return new self(self::HANDOFF, $text);
    }

    public static function refused(string $text): self
    {
        return new self(self::REFUSED, $text);
    }

    public function needsConfirmation(): bool
    {
        return $this->type === self::CONFIRM;
    }
}
