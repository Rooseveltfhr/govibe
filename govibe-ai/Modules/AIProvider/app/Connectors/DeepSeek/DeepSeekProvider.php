<?php

namespace Modules\AIProvider\Connectors\DeepSeek;

use Modules\AIProvider\Connectors\OpenAiCompatibleProvider;

class DeepSeekProvider extends OpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'deepseek';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.deepseek.com/v1';
    }
}
