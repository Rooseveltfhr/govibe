<?php

namespace Modules\Agents\Runtime;

/**
 * Yon sèl mesaj nan yon konvèsasyon.
 *
 * Nou sere wòl la ak tèks la — sa modèl la bezwen — epi metadone yo apa
 * (founisè, modèl, latans). Metadone yo pa janm ale nan modèl la: yo la
 * pou nou montre machann nan sa ki pase, pa pou nou peye jeton sou yo.
 */
final readonly class ConversationTurn
{
    public const USER = 'user';

    public const ASSISTANT = 'assistant';

    /** @param array<string, mixed> $meta */
    public function __construct(
        public string $role,
        public string $content,
        public array $meta = [],
    ) {}

    public static function user(string $content, bool $spoken = false): self
    {
        return new self(self::USER, $content, $spoken ? ['spoken' => true] : []);
    }

    /** @param array<string, mixed> $meta */
    public static function assistant(string $content, array $meta = []): self
    {
        return new self(self::ASSISTANT, $content, $meta);
    }

    public function wasSpoken(): bool
    {
        return ($this->meta['spoken'] ?? false) === true;
    }

    /** @return array{role: string, content: string, meta: array<string, mixed>} */
    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content, 'meta' => $this->meta];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $role = is_string($data['role'] ?? null) ? $data['role'] : self::USER;

        return new self(
            role: $role === self::ASSISTANT ? self::ASSISTANT : self::USER,
            content: is_string($data['content'] ?? null) ? $data['content'] : '',
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }
}
