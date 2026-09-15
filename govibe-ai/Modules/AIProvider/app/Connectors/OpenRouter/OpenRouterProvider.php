<?php

namespace Modules\AIProvider\Connectors\OpenRouter;

use Modules\AIProvider\Connectors\OpenAiCompatibleProvider;

/**
 * OpenRouter agrège des centaines de modèles derrière le dialecte OpenAI :
 * c'est par lui que GOVIBE AI atteint Llama, Gemma et Qwen sans écrire un
 * connecteur par éditeur.
 */
class OpenRouterProvider extends OpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'openrouter';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $referer = $this->config['site_url'] ?? null;

        return array_merge(parent::headers(), [
            // En-têtes d'attribution recommandés par OpenRouter.
            'HTTP-Referer' => is_string($referer) ? $referer : 'https://govibe.ai',
            'X-Title' => 'GOVIBE AI',
        ]);
    }
}
