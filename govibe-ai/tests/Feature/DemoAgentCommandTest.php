<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

it('refuses to run without any configured AI provider', function () {
    app()->instance(ProviderRegistry::class, new ProviderRegistry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);

    $this->artisan('agents:demo', ['sector' => 'restaurant', 'name' => 'Ti Kafe'])
        ->assertFailed();
});

it('prints a transcript when a provider is configured', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('demo', reply: 'Nou louvri 10h-22h.'));

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);

    $this->artisan('agents:demo', [
        'sector' => 'restaurant',
        'name' => 'Ti Kafe',
        '--question' => ['Ki lè nou louvri?'],
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Nou louvri 10h-22h.');
});

it('rejects an unknown sector with the list of what is available', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('demo'));
    app()->instance(ProviderRegistry::class, $registry);

    $this->artisan('agents:demo', ['sector' => 'hospital', 'name' => 'X'])
        ->assertFailed()
        ->expectsOutputToContain('restaurant');
});

it('transcribes an audio file and uses it as the demo question', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider(
        'demo',
        reply: 'Nou louvri 10h-22h.',
        transcript: 'Ki lè nou louvri?',
    ));

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);

    // Nòt: chak expectsOutputToContain isit la dwe matche yon liy diferan.
    // Si de chèk yo matche menm liy tablo a (kesyon + repons sou menm liy),
    // Mockery kredite premye ekspektasyon anrejistre a sèlman pou liy sa a,
    // e dezyèm nan pa janm jwenn matche l — se yon karakteristik Mockery
    // (yon apèl doWrite = yon sèl ekspektasyon kredite), pa yon bug nan
    // kòmand lan. Se poutèt sa nou chèk baniè a (inik) ak repons lan (inik)
    // separeman, pa kesyon an ki repete sou liy tablo a.
    $this->artisan('agents:demo', [
        'sector' => 'restaurant',
        'name' => 'Ti Kafe',
        '--audio' => __FILE__,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Vwa transkri')
        ->expectsOutputToContain('Nou louvri 10h-22h.')
        ->expectsOutputToContain('soti nan vwa');
});

it('reports a missing audio file cleanly', function () {
    $this->artisan('agents:demo', [
        'sector' => 'restaurant',
        'name' => 'Ti Kafe',
        '--audio' => '/tmp/pa-egziste-'.uniqid().'.wav',
    ])->assertFailed();
});

it('reports when no transcription-capable provider is configured', function () {
    $registry = new ProviderRegistry;
    app()->instance(ProviderRegistry::class, $registry);

    $this->artisan('agents:demo', [
        'sector' => 'restaurant',
        'name' => 'Ti Kafe',
        '--audio' => __FILE__,
    ])
        ->assertFailed()
        ->expectsOutputToContain('transkripsyon');
});
