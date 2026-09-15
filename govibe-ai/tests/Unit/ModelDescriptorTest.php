<?php

use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Exceptions\ProviderException;

it('computes cost in micro-usd from token counts', function () {
    $model = new ModelDescriptor(
        key: 'm',
        providerKey: 'p',
        capabilities: [Capability::Chat],
        inputPricePerMillion: 150_000,   // 0,15 USD / M jetons
        outputPricePerMillion: 600_000,  // 0,60 USD / M jetons
    );

    // 1000 jetons d'entrée + 500 de sortie = 150 + 300 micro-USD.
    expect($model->costMicro(1000, 500))->toBe(450);
});

it('returns the language specific quality when available', function () {
    $model = new ModelDescriptor(
        key: 'm',
        providerKey: 'p',
        capabilities: [Capability::Chat],
        inputPricePerMillion: 0,
        outputPricePerMillion: 0,
        quality: 80,
        qualityByLanguage: ['ht' => 45],
    );

    expect($model->qualityFor('ht'))->toBe(45)
        ->and($model->qualityFor('fr'))->toBe(80)
        ->and($model->qualityFor(null))->toBe(80);
});

it('builds a public identifier', function () {
    $model = new ModelDescriptor('gpt-4o-mini', 'openai', [Capability::Chat], 0, 0);

    expect($model->publicId())->toBe('openai/gpt-4o-mini');
});

it('hydrates from a manifest entry', function () {
    $model = ModelDescriptor::fromManifest('openai', [
        'key' => 'gpt-4o',
        'capabilities' => ['chat', 'vision'],
        'input_price_per_million' => 2_500_000,
        'output_price_per_million' => 10_000_000,
        'quality_by_language' => ['ht' => 68],
    ]);

    expect($model->providerKey)->toBe('openai')
        ->and($model->supports(Capability::Vision))->toBeTrue()
        ->and($model->supports(Capability::Speech))->toBeFalse()
        ->and($model->qualityFor('ht'))->toBe(68);
});

it('marks server errors as retryable and client errors as final', function () {
    expect(ProviderException::fromHttpStatus('openai', 500, 'boom')->retryable)->toBeTrue()
        ->and(ProviderException::fromHttpStatus('openai', 429, 'slow down')->retryable)->toBeTrue()
        ->and(ProviderException::fromHttpStatus('openai', 400, 'bad request')->retryable)->toBeFalse();
});
