<?php

/*
 * Manifeste OpenAI.
 *
 * Les prix sont exprimés en micro-USD par million de jetons (entiers, jamais
 * de flottants pour l'argent) et servent de valeurs par défaut : la table
 * `ai_models` les surcharge, ce qui permet de les ajuster depuis
 * l'administration sans déploiement.
 *
 * `quality_by_language` note la qualité observée par langue (0..100) — c'est
 * ce qui permet au routeur d'envoyer le créole vers les modèles qui le
 * gèrent réellement.
 */

return [
    'name' => 'OpenAI',
    'models' => [
        [
            'key' => 'gpt-4o-mini',
            'name' => 'GPT-4o mini',
            'capabilities' => ['chat', 'vision'],
            'input_price_per_million' => 150_000,
            'output_price_per_million' => 600_000,
            'context_window' => 128_000,
            'quality' => 72,
            'quality_by_language' => ['fr' => 74, 'en' => 78, 'es' => 74, 'ht' => 52],
            'expected_latency_ms' => 1200,
        ],
        [
            'key' => 'gpt-4o',
            'name' => 'GPT-4o',
            'capabilities' => ['chat', 'vision'],
            'input_price_per_million' => 2_500_000,
            'output_price_per_million' => 10_000_000,
            'context_window' => 128_000,
            'quality' => 88,
            'quality_by_language' => ['fr' => 90, 'en' => 92, 'es' => 90, 'ht' => 68],
            'expected_latency_ms' => 2200,
        ],
        [
            // Pri odyo se pa milyon jeton (pa gen jeton antre/sòti isit la) —
            // se pri pa minit reyèl OpenAI a; 0 isit la se yon valè kwatye,
            // pa yon pri egzat. Modèl sa a pa antre nan chwa AiRouter la
            // (li pa gen kapasite 'chat') — TranscriptionService jwenn li
            // dirèkteman pa premye founisè konfigire ki sipòte transkripsyon.
            'key' => 'whisper-1',
            'name' => 'Whisper',
            'capabilities' => ['transcription'],
            'input_price_per_million' => 0,
            'output_price_per_million' => 0,
            'quality_by_language' => ['fr' => 85, 'en' => 90, 'es' => 85, 'ht' => 40],
        ],
    ],
];
