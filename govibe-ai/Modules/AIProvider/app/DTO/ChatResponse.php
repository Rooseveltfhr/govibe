<?php

namespace Modules\AIProvider\DTO;

final readonly class ChatResponse
{
    public function __construct(
        public string $content,
        public string $model,
        public string $providerKey,
        public TokenUsage $usage,
        public string $finishReason = 'stop',
        public ?string $externalId = null,
    ) {}
}
