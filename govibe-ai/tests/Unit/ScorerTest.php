<?php

use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\DTO\ProviderHealth;
use Modules\AIProvider\Enums\Capability;
use Modules\AIRouter\DTO\Candidate;
use Modules\AIRouter\DTO\RoutingContext;
use Modules\AIRouter\Routing\Scorer;

function model(
    string $provider,
    int $inputPrice,
    int $quality = 50,
    int $latency = 2000,
    array $qualityByLanguage = [],
): ModelDescriptor {
    return new ModelDescriptor(
        key: $provider.'-m',
        providerKey: $provider,
        capabilities: [Capability::Chat],
        inputPricePerMillion: $inputPrice,
        outputPricePerMillion: $inputPrice * 2,
        contextWindow: 128_000,
        quality: $quality,
        qualityByLanguage: $qualityByLanguage,
        expectedLatencyMs: $latency,
    );
}

function candidate(ModelDescriptor $model, ?ProviderHealth $health = null): Candidate
{
    return new Candidate($model, $health ?? ProviderHealth::unknown($model->providerKey));
}

it('prefers the cheapest model under the cost strategy', function () {
    $scorer = new Scorer;

    $ranked = $scorer->rank([
        candidate(model('cher', 5_000_000, quality: 95)),
        candidate(model('bon-marche', 100_000, quality: 60)),
    ], new RoutingContext(Capability::Chat, strategy: 'cost', estimatedInputTokens: 1000));

    expect($ranked[0]->providerKey())->toBe('bon-marche');
});

it('prefers the best model under the quality strategy', function () {
    $scorer = new Scorer;

    $ranked = $scorer->rank([
        candidate(model('cher', 5_000_000, quality: 95)),
        candidate(model('bon-marche', 100_000, quality: 60)),
    ], new RoutingContext(Capability::Chat, strategy: 'quality', estimatedInputTokens: 1000));

    expect($ranked[0]->providerKey())->toBe('cher');
});

it('routes creole to the model that actually handles it', function () {
    $scorer = new Scorer;

    // Même prix, même latence : seule la qualité en créole les distingue.
    $ranked = $scorer->rank([
        candidate(model('generique', 1_000_000, quality: 80, qualityByLanguage: ['ht' => 30])),
        candidate(model('creolophone', 1_000_000, quality: 70, qualityByLanguage: ['ht' => 85])),
    ], new RoutingContext(Capability::Chat, strategy: 'quality', language: 'ht', estimatedInputTokens: 500));

    expect($ranked[0]->providerKey())->toBe('creolophone');
});

it('prefers the fastest observed provider under the speed strategy', function () {
    $scorer = new Scorer;

    $ranked = $scorer->rank([
        // Le manifeste annonce 500 ms mais la mesure réelle dit 9000 ms.
        candidate(
            model('optimiste', 1_000_000, latency: 500),
            new ProviderHealth('optimiste', true, 0.0, 9000, 50),
        ),
        candidate(
            model('regulier', 1_000_000, latency: 3000),
            new ProviderHealth('regulier', true, 0.0, 800, 50),
        ),
    ], new RoutingContext(Capability::Chat, strategy: 'speed', estimatedInputTokens: 500));

    expect($ranked[0]->providerKey())->toBe('regulier');
});

it('penalises a provider with a high error rate', function () {
    $scorer = new Scorer;

    $ranked = $scorer->rank([
        candidate(model('instable', 1_000_000), new ProviderHealth('instable', true, 0.9, 900, 100)),
        candidate(model('fiable', 1_000_000), new ProviderHealth('fiable', true, 0.0, 900, 100)),
    ], new RoutingContext(Capability::Chat, estimatedInputTokens: 500));

    expect($ranked[0]->providerKey())->toBe('fiable');
});

it('honours user provider preferences in order', function () {
    $scorer = new Scorer;

    $ranked = $scorer->rank([
        candidate(model('a', 1_000_000)),
        candidate(model('b', 1_000_000)),
    ], new RoutingContext(Capability::Chat, preferredProviders: ['b', 'a'], estimatedInputTokens: 500));

    expect($ranked[0]->providerKey())->toBe('b');
});

it('returns an empty ranking for an empty candidate list', function () {
    expect((new Scorer)->rank([], new RoutingContext(Capability::Chat)))->toBe([]);
});

it('does not penalise anyone when candidates are identical', function () {
    $scorer = new Scorer;

    $ranked = $scorer->rank([
        candidate(model('a', 1_000_000, quality: 70)),
        candidate(model('b', 1_000_000, quality: 70)),
    ], new RoutingContext(Capability::Chat, estimatedInputTokens: 500));

    expect($ranked[0]->score)->toEqualWithDelta($ranked[1]->score, 0.0001);
});

it('uses configured balanced weights', function () {
    // Poids « tout sur le coût » : le moins cher doit gagner malgré une
    // qualité inférieure, même en stratégie équilibrée.
    $scorer = new Scorer(['cost' => 1.0, 'speed' => 0.0, 'quality' => 0.0, 'preference' => 0.0, 'availability' => 0.0]);

    $ranked = $scorer->rank([
        candidate(model('cher', 9_000_000, quality: 99)),
        candidate(model('bon-marche', 10_000, quality: 10)),
    ], new RoutingContext(Capability::Chat, estimatedInputTokens: 1000));

    expect($ranked[0]->providerKey())->toBe('bon-marche');
});
