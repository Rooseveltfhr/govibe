<?php

use Modules\AIProvider\Connectors\Anthropic\AnthropicProvider;
use Modules\AIProvider\Connectors\DeepSeek\DeepSeekProvider;
use Modules\AIProvider\Connectors\ElevenLabs\ElevenLabsProvider;
use Modules\AIProvider\Connectors\Gemini\GeminiProvider;
use Modules\AIProvider\Connectors\HuggingFace\HuggingFaceProvider;
use Modules\AIProvider\Connectors\Mistral\MistralProvider;
use Modules\AIProvider\Connectors\OpenAI\OpenAiProvider;
use Modules\AIProvider\Connectors\OpenRouter\OpenRouterProvider;

return [
    'name' => 'AIProvider',

    /*
    |--------------------------------------------------------------------------
    | Fournisseurs enregistrés
    |--------------------------------------------------------------------------
    | Ajouter un fournisseur = créer son dossier de connecteur (classe +
    | manifest.php) puis ajouter une entrée ici. Aucun autre fichier du cœur
    | n'est modifié : ni le routeur, ni les services, ni les contrôleurs.
    |
    | Un fournisseur sans clé d'API est ignoré par le routeur
    | (`isConfigured()` renvoie false) : la plateforme démarre donc sans
    | aucun identifiant.
    */

    'providers' => [

        'openai' => [
            'class' => OpenAiProvider::class,
            'enabled' => env('OPENAI_ENABLED', true),
            'api_key' => env('OPENAI_API_KEY', ''),
            'base_url' => env('OPENAI_BASE_URL'),
        ],

        'anthropic' => [
            'class' => AnthropicProvider::class,
            'enabled' => env('ANTHROPIC_ENABLED', true),
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'base_url' => env('ANTHROPIC_BASE_URL'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],

        'gemini' => [
            'class' => GeminiProvider::class,
            'enabled' => env('GEMINI_ENABLED', true),
            'api_key' => env('GEMINI_API_KEY', ''),
            'base_url' => env('GEMINI_BASE_URL'),
        ],

        'deepseek' => [
            'class' => DeepSeekProvider::class,
            'enabled' => env('DEEPSEEK_ENABLED', true),
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'base_url' => env('DEEPSEEK_BASE_URL'),
        ],

        'mistral' => [
            'class' => MistralProvider::class,
            'enabled' => env('MISTRAL_ENABLED', true),
            'api_key' => env('MISTRAL_API_KEY', ''),
            'base_url' => env('MISTRAL_BASE_URL'),
        ],

        'openrouter' => [
            'class' => OpenRouterProvider::class,
            'enabled' => env('OPENROUTER_ENABLED', true),
            'api_key' => env('OPENROUTER_API_KEY', ''),
            'base_url' => env('OPENROUTER_BASE_URL'),
            'site_url' => env('APP_URL', 'https://govibe.ai'),
        ],

        // Vwa (ak transkripsyon). Pa yon founisè chat: manifest la pa
        // deklare kapasite 'chat', kidonk router la pa janm chwazi l pou
        // reponn — li chwazi l pou pale.
        'elevenlabs' => [
            'class' => ElevenLabsProvider::class,
            'enabled' => env('ELEVENLABS_ENABLED', true),
            'api_key' => env('ELEVENLABS_API_KEY', ''),
            'base_url' => env('ELEVENLABS_BASE_URL'),
            'voice_id' => env('ELEVENLABS_VOICE_ID', ''),
        ],

        'huggingface' => [
            'class' => HuggingFaceProvider::class,
            'enabled' => env('HUGGINGFACE_ENABLED', true),
            'api_key' => env('HUGGINGFACE_API_KEY', ''),
            'base_url' => env('HUGGINGFACE_BASE_URL'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Disjoncteur (circuit breaker)
    |--------------------------------------------------------------------------
    */

    'circuit_breaker' => [
        'failure_threshold' => (int) env('GOVIBE_CB_FAILURES', 5),
        'cooldown_seconds' => (int) env('GOVIBE_CB_COOLDOWN', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Métriques de santé (fenêtre glissante)
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'window_seconds' => (int) env('GOVIBE_METRICS_WINDOW', 300),
    ],
];
