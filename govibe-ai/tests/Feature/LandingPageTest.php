<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\Templates\AgentTemplateRegistry;
use Modules\Agents\Templates\TemplateDescriptor;
use Modules\AIProvider\Connectors\ElevenLabs\ElevenLabsProvider;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

/** @param list<object> $providers */
function useLandingProviders(array $providers = []): void
{
    $registry = new ProviderRegistry;

    foreach ($providers as $provider) {
        $registry->register($provider);
    }

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

// Fraz sa a se pwomès la. Si li disparèt nan yon refonte, paj la pèdi tèt li.
it('opens with the promise, in the display face', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Trouver vos employés moins cher pour votre entreprise.')
        ->assertSee('fonts.googleapis.com/css2?family=Anton', false);
});

it('carries a menu that reaches every part of the site', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('orders.create'))
        ->assertSee(route('agents.index'))
        ->assertSee('#chatbots', false)
        ->assertSee('#agents', false)
        ->assertSee('#support', false);
});

// De blòk, de kalite. Yon chatbot reponn, yon ajan aji — epi paj la dwe di l.
it('shows a block of chatbots and a block of agents', function () {
    $registry = new AgentTemplateRegistry;

    expect($registry->ofKind(TemplateDescriptor::KIND_CHATBOT))->not->toBeEmpty()
        ->and($registry->ofKind(TemplateDescriptor::KIND_AGENT))->not->toBeEmpty();

    $response = $this->get('/')->assertOk();

    foreach ($registry->all() as $template) {
        $response->assertSee($template->label, false);
    }
});

it('gives every model an order button and a demo button', function () {
    $registry = new AgentTemplateRegistry;
    $response = $this->get('/')->assertOk();

    foreach ($registry->all() as $template) {
        $response->assertSee(route('orders.create', $template->sector), false);
        $response->assertSee(route('agents.demo', $template->sector), false);
    }
});

it('names the integrations a business will ask about', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('WhatsApp')
        ->assertSee(__('Site web'));
});

// Sou paj akèy la tou, yon kle vwa pou kont li pa dwe fè kwè chat la mache.
it('warns about the missing AI key even when the voice key is present', function () {
    useLandingProviders([new ElevenLabsProvider(['api_key' => 'xi-test'])]);

    $this->get('/')
        ->assertOk()
        ->assertSee(__("Aucune clé d'IA n'est configurée sur ce serveur : ce chat renverra une erreur au lieu d'une réponse inventée."));
});

it('drops the warning once a chat provider is configured', function () {
    useLandingProviders([new FakeChatProvider('demo')]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee(__("Aucune clé d'IA n'est configurée sur ce serveur : ce chat renverra une erreur au lieu d'une réponse inventée."));
});
