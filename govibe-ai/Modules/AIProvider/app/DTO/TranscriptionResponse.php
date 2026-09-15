<?php

namespace Modules\AIProvider\DTO;

final readonly class TranscriptionResponse
{
    public function __construct(
        public string $text,
        public string $providerKey,
        public ?string $language = null,
    ) {}
}
