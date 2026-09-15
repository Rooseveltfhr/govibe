<?php

namespace Modules\AIProvider\DTO;

use InvalidArgumentException;

/**
 * Message normalisé : tous les connecteurs traduisent vers/depuis cette forme.
 */
final readonly class ChatMessage
{
    public const ROLES = ['system', 'user', 'assistant', 'tool'];

    public function __construct(
        public string $role,
        public string $content,
    ) {
        if (! in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException("Rôle de message inconnu : {$role}");
        }
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $role = isset($payload['role']) && is_string($payload['role']) ? $payload['role'] : 'user';
        $content = $payload['content'] ?? '';

        return new self($role, is_string($content) ? $content : json_encode($content, JSON_THROW_ON_ERROR));
    }

    /** @return array{role: string, content: string} */
    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
