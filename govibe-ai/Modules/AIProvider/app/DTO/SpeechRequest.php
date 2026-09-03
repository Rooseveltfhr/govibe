<?php

namespace Modules\AIProvider\DTO;

/**
 * Yon demann pou di yon tèks ak vwa.
 *
 * `voice` se idantifyan vwa a bò kote founisè a (chak biznis ka gen pa l).
 * `language` sèvi pou konektè ki chwazi yon modèl selon lang lan — li pa
 * tradui anyen.
 */
final readonly class SpeechRequest
{
    public function __construct(
        public string $text,
        public ?string $voice = null,
        public ?string $model = null,
        public ?string $language = null,
    ) {}
}
