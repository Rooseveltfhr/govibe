<?php

namespace Modules\AIProvider\Contracts;

use Modules\AIProvider\DTO\TranscriptionRequest;
use Modules\AIProvider\DTO\TranscriptionResponse;

interface SupportsTranscription
{
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse;
}
