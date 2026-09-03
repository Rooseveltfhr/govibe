<?php

namespace Modules\AIProvider\Contracts;

use Modules\AIProvider\DTO\SpeechRequest;
use Modules\AIProvider\DTO\SpeechResponse;

/**
 * Sentèz vokal (tèks → vwa). Menm patèn ak SupportsChat: yon konektè
 * implemante l sèlman si li konnen fè l.
 */
interface SupportsSpeech
{
    public function speak(SpeechRequest $request): SpeechResponse;
}
