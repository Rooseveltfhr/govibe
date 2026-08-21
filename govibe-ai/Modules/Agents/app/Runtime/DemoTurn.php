<?php

namespace Modules\Agents\Runtime;

/** Yon echanj nan yon konvèsasyon demo: kesyon, repons, ak sa ki repons lan. */
final readonly class DemoTurn
{
    public function __construct(
        public string $question,
        public string $answer,
        public string $provider,
        public string $model,
        public int $latencyMs,
    ) {}
}
