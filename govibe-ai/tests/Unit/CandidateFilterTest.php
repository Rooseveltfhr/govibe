<?php

use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;
use Modules\AIRouter\DTO\RoutingContext;
use Modules\AIRouter\Routing\CandidateFilter;

function descriptor(string $provider, string $key = 'm', int $contextWindow = 128_000, array $capabilities = [Capability::Chat]): ModelDescriptor
{
    return new ModelDescriptor(
        key: $key,
        providerKey: $provider,
        capabilities: $capabilities,
        inputPricePerMillion: 1_000_000,
        outputPricePerMillion: 2_000_000,
        contextWindow: $contextWindow,
    );
}

it('keeps only models supporting the requested capability', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('a'), descriptor('b', capabilities: [Capability::Images])],
        new RoutingContext(Capability::Chat),
    );

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->providerKey())->toBe('a');
});

it('excludes blocked providers', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('a'), descriptor('b')],
        new RoutingContext(Capability::Chat, blockedProviders: ['a']),
    );

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->providerKey())->toBe('b');
});

it('excludes providers whose circuit breaker is open', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('a'), descriptor('b')],
        new RoutingContext(Capability::Chat),
        breakerAllows: ['a' => false, 'b' => true],
    );

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->providerKey())->toBe('b');
});

it('keeps only the forced model when one is requested', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('a', 'gpt-4o-mini'), descriptor('b', 'claude')],
        new RoutingContext(Capability::Chat, forcedModel: 'a/gpt-4o-mini'),
    );

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->model->key)->toBe('gpt-4o-mini');
});

it('accepts a bare model name as forced model', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('a', 'gpt-4o-mini'), descriptor('b', 'claude')],
        new RoutingContext(Capability::Chat, forcedModel: 'gpt-4o-mini'),
    );

    expect($filtered)->toHaveCount(1);
});

it('drops models whose context window is too small', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('petit', 'm', contextWindow: 4_000), descriptor('grand', 'm', contextWindow: 200_000)],
        new RoutingContext(Capability::Chat, estimatedInputTokens: 10_000, estimatedOutputTokens: 2_000),
    );

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]->providerKey())->toBe('grand');
});

it('returns nothing when everything is filtered out', function () {
    $filtered = (new CandidateFilter)->filter(
        [descriptor('a')],
        new RoutingContext(Capability::Chat, blockedProviders: ['a']),
    );

    expect($filtered)->toBe([]);
});
