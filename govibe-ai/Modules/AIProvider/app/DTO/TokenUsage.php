<?php

namespace Modules\AIProvider\DTO;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    public function total(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /** @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int} */
    public function toOpenAiArray(): array
    {
        return [
            'prompt_tokens' => $this->inputTokens,
            'completion_tokens' => $this->outputTokens,
            'total_tokens' => $this->total(),
        ];
    }
}
