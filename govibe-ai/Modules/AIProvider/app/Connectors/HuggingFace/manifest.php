<?php

return [
    'name' => 'Hugging Face',
    'models' => [
        [
            'key' => 'meta-llama/Llama-3.1-8B-Instruct',
            'name' => 'Llama 3.1 8B Instruct',
            'capabilities' => ['chat'],
            'input_price_per_million' => 30_000,
            'output_price_per_million' => 60_000,
            'context_window' => 128_000,
            'quality' => 58,
            'quality_by_language' => ['fr' => 58, 'en' => 66, 'es' => 58, 'ht' => 32],
            'expected_latency_ms' => 1400,
        ],
    ],
];
