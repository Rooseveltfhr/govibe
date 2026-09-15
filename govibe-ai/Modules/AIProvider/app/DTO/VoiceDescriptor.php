<?php

namespace Modules\AIProvider\DTO;

/**
 * Yon vwa nan bibliyotèk kont lan.
 *
 * `category` di kote vwa a soti: `premade` (sa founisè a bay), `cloned`
 * (yon vwa ou anrejistre ou menm), `professional`, `generated`. Diferans
 * lan konte pou machann nan: yon vwa klonaj se vwa PA L — se sa ki fè yon
 * ajan sonnen tankou biznis lan olye li sonnen tankou tout lòt moun.
 */
final readonly class VoiceDescriptor
{
    public const CLONED = 'cloned';

    /** @param array<string, string> $labels */
    public function __construct(
        public string $id,
        public string $name,
        public string $providerKey,
        public string $category = 'premade',
        public ?string $description = null,
        public ?string $previewUrl = null,
        public array $labels = [],
    ) {}

    public function isMine(): bool
    {
        return in_array($this->category, [self::CLONED, 'professional', 'generated'], true);
    }

    /**
     * Konstwi depi repons founisè a, tolerab: yon chan ki manke pa dwe
     * fè lis la tonbe — nou pito yon vwa san deskripsyon pase zewo vwa.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $providerKey): ?self
    {
        $id = $data['voice_id'] ?? $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $labels = [];

        foreach ((array) ($data['labels'] ?? []) as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $labels[$key] = (string) $value;
            }
        }

        return new self(
            id: $id,
            name: is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : $id,
            providerKey: $providerKey,
            category: is_string($data['category'] ?? null) ? $data['category'] : 'premade',
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            previewUrl: is_string($data['preview_url'] ?? null) ? $data['preview_url'] : null,
            labels: $labels,
        );
    }
}
