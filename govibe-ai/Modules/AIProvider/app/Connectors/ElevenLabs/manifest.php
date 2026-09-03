<?php

/*
 * Manifeste ElevenLabs — vwa ak transkripsyon sèlman.
 *
 * Okenn kapasite « chat » isit la: se sa ki anpeche AiRouter chwazi
 * ElevenLabs pou reponn yon mesaj. Li pale, li pa panse.
 *
 * Pri: ElevenLabs faktire pa karaktè (vwa) ak pa minit (transkripsyon), pa
 * pa jeton. Kolòn pri pa milyon jeton yo pa gen sans isit la — nou kite yo
 * a 0 olye nou envante yon konvèsyon. Kalkil depans vokal la se yon travay
 * apa (Usage), li pa pase nan scoring router la.
 */

return [
    'name' => 'ElevenLabs',
    'models' => [
        [
            'key' => 'eleven_multilingual_v2',
            'name' => 'ElevenLabs Multilingual v2',
            'capabilities' => ['speech'],
            'input_price_per_million' => 0,
            'output_price_per_million' => 0,
            // Kreyòl pa nan lang ElevenLabs sètifye yo: vwa a li tèks la
            // byen (fonetik li pre franse), men nou pa deklare l pi wo pase
            // sa nou ka pwouve.
            'quality_by_language' => ['fr' => 85, 'en' => 88, 'es' => 85, 'ht' => 60],
            'expected_latency_ms' => 1500,
        ],
        [
            'key' => 'scribe_v1',
            'name' => 'ElevenLabs Scribe',
            'capabilities' => ['transcription'],
            'input_price_per_million' => 0,
            'output_price_per_million' => 0,
            'quality_by_language' => ['fr' => 85, 'en' => 88, 'es' => 85, 'ht' => 45],
            'expected_latency_ms' => 2000,
        ],
    ],
];
