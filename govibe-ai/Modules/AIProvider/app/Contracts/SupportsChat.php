<?php

namespace Modules\AIProvider\Contracts;

use Generator;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\StreamChunk;

interface SupportsChat
{
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * Diffusion incrémentale des jetons.
     *
     * @return Generator<int, StreamChunk>
     */
    public function streamChat(ChatRequest $request): Generator;
}
