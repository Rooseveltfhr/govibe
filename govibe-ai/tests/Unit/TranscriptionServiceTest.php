<?php

use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIServices\Transcription\TranscriptionService;
use Tests\Support\FakeChatProvider;

it('transcribes using the first configured provider that supports it', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('demo', transcript: 'Konbyen griyo a koute?'));

    $service = new TranscriptionService($registry);
    $result = $service->transcribe(__FILE__, 'ht');

    expect($result)->toBe([
        'text' => 'Konbyen griyo a koute?',
        'provider' => 'demo',
        'language' => 'ht',
    ]);
});

it('throws when no configured provider supports transcription', function () {
    $service = new TranscriptionService(new ProviderRegistry);

    expect(fn () => $service->transcribe(__FILE__))
        ->toThrow(NoProviderAvailableException::class);
});
