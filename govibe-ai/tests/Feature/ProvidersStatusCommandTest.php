<?php

use Modules\AIProvider\Registry\ProviderRegistry;
use Tests\Support\FakeChatProvider;

function useStatusRegistry(ProviderRegistry $registry): void
{
    app()->instance(ProviderRegistry::class, $registry);
}

// Se kòmand sa a ki reponn « poukisa demo a di pa gen founisè? » sou sèvè a.
// Li dwe echwe lè zewo founisè konfigire, sinon deplwaman an ta pase vèt ak
// yon platfòm ki pa ka reponn — menm erè ak APP_KEY la.
it('fails and says so when no provider has credentials', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('openai', configured: false));
    useStatusRegistry($registry);

    $this->artisan('govibe:providers')
        ->expectsOutputToContain('Zewo founisè konfigire')
        ->assertExitCode(1);
});

it('succeeds and counts the providers that do have credentials', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('openai', configured: true));
    $registry->register(new FakeChatProvider('mistral', configured: false));
    useStatusRegistry($registry);

    $this->artisan('govibe:providers')
        ->expectsOutputToContain('1 founisè konfigire.')
        ->assertExitCode(0);
});

// Yon kle pa dwe janm rive nan yon jounal deplwaman.
it('never prints a credential', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('openai', configured: true));
    useStatusRegistry($registry);

    $this->artisan('govibe:providers')
        ->doesntExpectOutputToContain('sk-')
        ->assertExitCode(0);
});
