<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\Models\Agent;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

function useCatalogProvider(?FakeChatProvider $provider = null): void
{
    $registry = new ProviderRegistry;

    if ($provider !== null) {
        $registry->register($provider);
    }

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

it('shows the sector catalogue with a Demo and a Create button', function () {
    $this->get(route('agents.index'))
        ->assertOk()
        ->assertSee('Restoran')
        ->assertSee('Lekòl')
        ->assertSee(route('agents.demo', 'restaurant'))
        ->assertSee(route('agents.create', 'restaurant'));
});

it('sends the site root to the catalogue', function () {
    $this->get('/')->assertRedirect('/agents');
});

it('offers a create form whose fields match the sector', function () {
    $this->get(route('agents.create', 'restaurant'))
        ->assertOk()
        ->assertSee('knowledge[menu]', false)
        ->assertSee('knowledge[horaires]', false);

    $this->get(route('agents.create', 'clinic'))
        ->assertOk()
        ->assertSee('knowledge[services]', false)
        ->assertDontSee('knowledge[menu]', false);
});

it('refuses a sector that is not in the catalogue', function () {
    $this->get(route('agents.create', 'hospital'))->assertNotFound();
    $this->get(route('agents.demo', 'hospital'))->assertNotFound();
});

it('creates an agent and keeps it', function () {
    $this->post(route('agents.store'), [
        'sector' => 'restaurant',
        'name' => 'Ti Kafe',
        'knowledge' => ['menu' => 'Griyo 500 G', 'horaires' => '10h-22h'],
        'handoff_to' => '+509 0000 0000',
    ])->assertRedirect();

    $agent = Agent::firstOrFail();

    expect($agent->key)->toBe('ti-kafe')
        ->and($agent->sector)->toBe('restaurant')
        ->and($agent->knowledge)->toBe(['menu' => 'Griyo 500 G', 'horaires' => '10h-22h']);
});

it('drops knowledge fields left blank rather than storing empty labels', function () {
    $this->post(route('agents.store'), [
        'sector' => 'restaurant',
        'name' => 'Ti Kafe',
        'knowledge' => ['menu' => 'Griyo', 'horaires' => '', 'adresse' => '   '],
    ]);

    expect(Agent::firstOrFail()->knowledge)->toBe(['menu' => 'Griyo']);
});

it('gives two businesses with the same name distinct keys', function () {
    foreach (range(1, 2) as $_) {
        $this->post(route('agents.store'), ['sector' => 'restaurant', 'name' => 'Chez Nou']);
    }

    expect(Agent::pluck('key')->all())->toBe(['chez-nou', 'chez-nou-2']);
});

it('rejects a missing name and an unknown sector', function () {
    $this->post(route('agents.store'), ['sector' => 'restaurant'])
        ->assertSessionHasErrors('name');

    $this->post(route('agents.store'), ['sector' => 'hospital', 'name' => 'X'])
        ->assertSessionHasErrors('sector');

    expect(Agent::count())->toBe(0);
});

it('shows the agent with what it knows and its safety rules', function () {
    $agent = Agent::create([
        'key' => 'ti-kafe', 'name' => 'Ti Kafe', 'sector' => 'restaurant',
        'knowledge' => ['menu' => 'Griyo 500 G'], 'handoff_to' => '+509 0000 0000',
    ]);

    $this->get(route('agents.show', $agent))
        ->assertOk()
        ->assertSee('Ti Kafe')
        ->assertSee('Griyo 500 G')
        ->assertSee('create_order')   // aksyon ki mande konfimasyon
        ->assertSee('get_menu')       // aksyon ki fèt dirèk
        ->assertSee('+509 0000 0000');
});

it('runs a demo through the real router and shows provider and latency', function () {
    useCatalogProvider(new FakeChatProvider('demo', reply: 'Nou louvri 10h-22h.'));

    $this->post(route('agents.demo', 'restaurant'), ['question' => 'Ki lè nou louvri?'])
        ->assertOk()
        ->assertSee('Ki lè nou louvri?')
        ->assertSee('Nou louvri 10h-22h.')
        ->assertSee('demo');
});

it('demos a saved agent, not just a blank template', function () {
    useCatalogProvider(new FakeChatProvider('demo', reply: 'Griyo a 500 goud.'));

    $agent = Agent::create([
        'key' => 'ti-kafe', 'name' => 'Ti Kafe', 'sector' => 'restaurant',
        'knowledge' => ['menu' => 'Griyo 500 G'],
    ]);

    $this->post(route('agents.demo', 'restaurant'), [
        'agent' => $agent->id,
        'question' => 'Konbyen griyo a?',
    ])
        ->assertOk()
        ->assertSee('Ti Kafe')
        ->assertSee('Griyo a 500 goud.');
});

it('says plainly that no AI key is configured instead of faking an answer', function () {
    useCatalogProvider(); // zewo founisè

    $response = $this->post(route('agents.demo', 'restaurant'), ['question' => 'Bonjou']);

    $response->assertOk()->assertSee(__("Aucun fournisseur d'IA n'est configuré sur ce serveur."));
});

it('warns on the catalogue when no AI key is configured', function () {
    useCatalogProvider();

    $this->get(route('agents.index'))->assertOk()->assertSee(__("Aucune clé d'IA n'est configurée sur ce serveur : le bouton Démo affichera une erreur au lieu d'une vraie réponse."));
});
