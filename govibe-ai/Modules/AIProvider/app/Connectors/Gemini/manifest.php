<?php

return [
    'name' => 'Google Gemini',
    'models' => [
        [
            'key' => 'gemini-2.0-flash',
            'name' => 'Gemini 2.0 Flash',
            'capabilities' => ['chat', 'vision'],
            'input_price_per_million' => 100_000,
            'output_price_per_million' => 400_000,
            'context_window' => 1_000_000,
            'quality' => 76,
            'quality_by_language' => ['fr' => 78, 'en' => 82, 'es' => 78, 'ht' => 58],
            'expected_latency_ms' => 1100,
        ],
        [
            'key' => 'gemini-1.5-pro',
            'name' => 'Gemini 1.5 Pro',
            'capabilities' => ['chat', 'vision'],
            'input_price_per_million' => 1_250_000,
            'output_price_per_million' => 5_000_000,
            'context_window' => 2_000_000,
            'quality' => 84,
            'quality_by_language' => ['fr' => 85, 'en' => 88, 'es' => 85, 'ht' => 62],
            'expected_latency_ms' => 2800,
        ],
    ],
];
