<?php

namespace Modules\AIProvider\Connectors\OpenAI;

use Modules\AIProvider\Connectors\OpenAiCompatibleProvider;

class OpenAiProvider extends OpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'openai';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }
}
