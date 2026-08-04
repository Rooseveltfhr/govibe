<?php

namespace Modules\AIProvider\Connectors\Mistral;

use Modules\AIProvider\Connectors\OpenAiCompatibleProvider;

class MistralProvider extends OpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'mistral';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.mistral.ai/v1';
    }
}
