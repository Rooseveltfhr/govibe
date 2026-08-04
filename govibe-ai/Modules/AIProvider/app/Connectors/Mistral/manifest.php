<?php

return [
    'name' => 'Mistral AI',
    'models' => [
        [
            'key' => 'mistral-small-latest',
            'name' => 'Mistral Small',
            'capabilities' => ['chat'],
            'input_price_per_million' => 200_000,
            'output_price_per_million' => 600_000,
            'context_window' => 128_000,
            'quality' => 70,
            // Modèle européen : le français est son point fort.
            'quality_by_language' => ['fr' => 80, 'en' => 74, 'es' => 74, 'ht' => 40],
            'expected_latency_ms' => 1300,
        ],
        [
            'key' => 'mistral-large-latest',
            'name' => 'Mistral Large',
            'capabilities' => ['chat'],
            'input_price_per_million' => 2_000_000,
            'output_price_per_million' => 6_000_000,
            'context_window' => 128_000,
            'quality' => 84,
            'quality_by_language' => ['fr' => 90, 'en' => 85, 'es' => 85, 'ht' => 50],
            'expected_latency_ms' => 2400,
        ],
    ],
];
