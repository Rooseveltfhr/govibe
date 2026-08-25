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
