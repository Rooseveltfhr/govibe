<?php

namespace Modules\AIProvider\Connectors\HuggingFace;

use Modules\AIProvider\Connectors\OpenAiCompatibleProvider;

/**
 * Routeur d'inférence Hugging Face, compatible avec le dialecte OpenAI.
 */
class HuggingFaceProvider extends OpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'huggingface';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://router.huggingface.co/v1';
    }
}
