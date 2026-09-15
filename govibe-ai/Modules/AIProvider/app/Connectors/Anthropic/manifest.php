<?php

return [
    'name' => 'Anthropic (Claude)',
    'models' => [
        [
            'key' => 'claude-haiku-4-5-20251001',
            'name' => 'Claude Haiku 4.5',
            'capabilities' => ['chat', 'vision'],
            'input_price_per_million' => 1_000_000,
            'output_price_per_million' => 5_000_000,
            'context_window' => 200_000,
            'quality' => 82,
            // Meilleure tenue observée en créole haïtien parmi les modèles rapides.
            'quality_by_language' => ['fr' => 86, 'en' => 88, 'es' => 85, 'ht' => 72],
            'expected_latency_ms' => 1400,
        ],
        [
            'key' => 'claude-sonnet-5',
            'name' => 'Claude Sonnet 5',
            'capabilities' => ['chat', 'vision'],
            'input_price_per_million' => 3_000_000,
            'output_price_per_million' => 15_000_000,
            'context_window' => 200_000,
            'quality' => 92,
            'quality_by_language' => ['fr' => 93, 'en' => 94, 'es' => 92, 'ht' => 80],
            'expected_latency_ms' => 2600,
        ],
    ],
];
