<?php

return [
    'name' => 'OpenRouter',
    'models' => [
        [
            'key' => 'meta-llama/llama-3.3-70b-instruct',
            'name' => 'Llama 3.3 70B Instruct',
            'capabilities' => ['chat'],
            'input_price_per_million' => 120_000,
            'output_price_per_million' => 300_000,
            'context_window' => 128_000,
            'quality' => 76,
            'quality_by_language' => ['fr' => 76, 'en' => 82, 'es' => 76, 'ht' => 48],
            'expected_latency_ms' => 1900,
        ],
        [
            'key' => 'google/gemma-2-27b-it',
            'name' => 'Gemma 2 27B',
            'capabilities' => ['chat'],
            'input_price_per_million' => 90_000,
            'output_price_per_million' => 90_000,
            'context_window' => 8_192,
            'quality' => 64,
            'quality_by_language' => ['fr' => 64, 'en' => 70, 'es' => 64, 'ht' => 38],
            'expected_latency_ms' => 1500,
        ],
        [
            'key' => 'qwen/qwen-2.5-72b-instruct',
            'name' => 'Qwen 2.5 72B Instruct',
            'capabilities' => ['chat'],
            'input_price_per_million' => 130_000,
            'output_price_per_million' => 400_000,
            'context_window' => 128_000,
            'quality' => 74,
            'quality_by_language' => ['fr' => 72, 'en' => 80, 'es' => 72, 'ht' => 40],
            'expected_latency_ms' => 2000,
        ],
    ],
];
