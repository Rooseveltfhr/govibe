<?php

namespace Modules\AIProvider\DTO;

/**
 * Fragment de réponse en diffusion. Le dernier fragment porte `done = true`
 * et, si le fournisseur le communique, la consommation de jetons.
 */
final readonly class StreamChunk
{
    public function __construct(
        public string $delta = '',
        public bool $done = false,
        public ?TokenUsage $usage = null,
        public ?string $finishReason = null,
    ) {}

    public static function end(?TokenUsage $usage = null, string $finishReason = 'stop'): self
    {
        return new self('', true, $usage, $finishReason);
    }
}
