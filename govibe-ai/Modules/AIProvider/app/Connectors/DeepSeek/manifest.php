<?php

return [
    'name' => 'DeepSeek',
    'models' => [
        [
            'key' => 'deepseek-chat',
            'name' => 'DeepSeek Chat',
            'capabilities' => ['chat'],
            'input_price_per_million' => 270_000,
            'output_price_per_million' => 1_100_000,
            'context_window' => 64_000,
            'quality' => 78,
            'quality_by_language' => ['fr' => 76, 'en' => 82, 'es' => 74, 'ht' => 44],
            'expected_latency_ms' => 1800,
        ],
        [
            'key' => 'deepseek-reasoner',
            'name' => 'DeepSeek Reasoner',
            'capabilities' => ['chat'],
            'input_price_per_million' => 550_000,
            'output_price_per_million' => 2_190_000,
            'context_window' => 64_000,
            'quality' => 85,
            'quality_by_language' => ['fr' => 82, 'en' => 88, 'es' => 80, 'ht' => 46],
            'expected_latency_ms' => 6000,
        ],
    ],
];
