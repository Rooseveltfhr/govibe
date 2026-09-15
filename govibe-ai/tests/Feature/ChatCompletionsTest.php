<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Modules\Usage\Models\AiRequest;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

/** Remplace le registre par des fournisseurs factices (aucun appel réseau). */
function useProviders(FakeChatProvider ...$providers): ProviderRegistry
{
    $registry = new ProviderRegistry;

    foreach ($providers as $provider) {
        $registry->register($provider);
    }

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);

    return $registry;
}

it('answers in the OpenAI response shape', function () {
    useProviders(new FakeChatProvider('demo'));

    $response = $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjou']],
    ]);

    $response->assertOk()
        ->assertJsonPath('object', 'chat.completion')
        ->assertJsonPath('choices.0.message.role', 'assistant')
        ->assertJsonPath('choices.0.message.content', 'Bonjou!')
        ->assertJsonPath('usage.prompt_tokens', 12)
        ->assertJsonPath('usage.total_tokens', 20)
        ->assertJsonPath('govibe.provider', 'demo');
});

it('records every call in the usage journal', function () {
    useProviders(new FakeChatProvider('demo'));

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjou, kijan ou ye?']],
    ])->assertOk();

    $record = AiRequest::query()->firstOrFail();

    expect($record->provider_key)->toBe('demo')
        ->and($record->status)->toBe('ok')
        ->and($record->getAttribute('input_tokens'))->toBe(12)
        // La langue détectée sert au routage : elle doit être journalisée.
        ->and($record->getAttribute('language'))->toBe('ht');
});

it('routes to the cheapest provider when asked for cost', function () {
    $cheap = new FakeChatProvider('bon-marche', inputPricePerMillion: 50_000, quality: 40);
    $expensive = new FakeChatProvider('cher', inputPricePerMillion: 9_000_000, quality: 99);

    useProviders($expensive, $cheap);

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjour']],
        'routing' => 'cost',
    ])->assertOk()->assertJsonPath('govibe.provider', 'bon-marche');

    expect($cheap->calls)->toBe(1)->and($expensive->calls)->toBe(0);
});

it('routes to the best provider when asked for quality', function () {
    useProviders(
        new FakeChatProvider('bon-marche', inputPricePerMillion: 50_000, quality: 40),
        new FakeChatProvider('cher', inputPricePerMillion: 9_000_000, quality: 99),
    );

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjour']],
        'routing' => 'quality',
    ])->assertOk()->assertJsonPath('govibe.provider', 'cher');
});

it('fails over to the next provider when one breaks down', function () {
    $broken = new FakeChatProvider(
        'casse',
        inputPricePerMillion: 10_000,
        failWith: ProviderException::fromHttpStatus('casse', 503, 'indisponible'),
    );
    $healthy = new FakeChatProvider('valide', inputPricePerMillion: 5_000_000);

    useProviders($broken, $healthy);

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjour']],
        'routing' => 'cost',
    ])->assertOk()->assertJsonPath('govibe.provider', 'valide');

    expect($broken->calls)->toBe(1)->and($healthy->calls)->toBe(1);

    $record = AiRequest::query()->firstOrFail();

    expect($record->status)->toBe('failover')
        ->and($record->getAttribute('attempts'))->toBe(2);
});

it('does not fail over on a client error', function () {
    $invalid = new FakeChatProvider(
        'strict',
        failWith: ProviderException::fromHttpStatus('strict', 400, 'requête invalide'),
    );
    $other = new FakeChatProvider('autre', inputPricePerMillion: 5_000_000);

    useProviders($invalid, $other);

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjour']],
        'routing' => 'cost',
    ])->assertStatus(400)->assertJsonPath('error.code', 'provider_error');

    // Rejouer une requête invalide ailleurs échouerait pareillement.
    expect($other->calls)->toBe(0);
});

it('returns 503 when no provider is configured', function () {
    useProviders();

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjou']],
    ])->assertStatus(503)->assertJsonPath('error.code', 'no_provider_available');
});

it('validates the request body', function () {
    useProviders(new FakeChatProvider('demo'));

    $this->postJson('/api/v1/chat/completions', ['messages' => []])
        ->assertStatus(422);

    $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Alo']],
        'routing' => 'inconnu',
    ])->assertStatus(422);
});

it('streams server-sent events', function () {
    useProviders(new FakeChatProvider('demo'));

    $response = $this->postJson('/api/v1/chat/completions', [
        'messages' => [['role' => 'user', 'content' => 'Bonjou']],
        'stream' => true,
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $body = $response->streamedContent();

    expect($body)->toContain('chat.completion.chunk')
        ->toContain('"content":"Bon"')
        ->toContain('data: [DONE]');

    $record = AiRequest::query()->firstOrFail();
    expect($record->getAttribute('streamed'))->toBeTrue();
});

it('lists the aggregated model catalogue', function () {
    useProviders(new FakeChatProvider('demo'));

    $this->getJson('/api/v1/models')
        ->assertOk()
        ->assertJsonPath('object', 'list')
        ->assertJsonPath('data.0.id', 'demo/demo-model')
        ->assertJsonPath('data.0.owned_by', 'demo');
});
