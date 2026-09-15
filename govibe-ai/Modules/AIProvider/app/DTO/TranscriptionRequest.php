<?php

namespace Modules\AIProvider\DTO;

/**
 * Yon demann pou transkri yon fichye odyo an tèks.
 */
final readonly class TranscriptionRequest
{
    public function __construct(
        public string $audioPath,
        public ?string $language = null,
        public ?string $model = null,
    ) {}
}
