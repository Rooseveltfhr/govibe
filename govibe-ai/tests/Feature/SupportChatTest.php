<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\Support\PlatformSupportAgent;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

function useSupportProvider(?FakeChatProvider $provider = null): void
{
    $registry = new ProviderRegistry;

    if ($provider !== null) {
        $registry->register($provider);
    }

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

it('answers a visitor question through the same runtime we sell', function () {
    useSupportProvider(new FakeChatProvider('demo', reply: 'Wi, ajan an pale kreyòl.'));

    $this->postJson(route('support.ask'), ['question' => 'Eske ajan an pale kreyòl?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Wi, ajan an pale kreyòl.')
        ->assertJsonPath('closed', false);
});

it('remembers the exchange between two questions', function () {
    useSupportProvider(new FakeChatProvider('demo', reply: 'Oke.'));

    $this->postJson(route('support.ask'), ['question' => 'Premye kesyon an']);
    $this->postJson(route('support.ask'), ['question' => 'Dezyèm nan'])->assertOk();

    $stored = session('louvia.support');

    expect($stored)->toBeArray()->toHaveCount(4);
});

it('says plainly that no AI key is configured instead of inventing an answer', function () {
    useSupportProvider();

    $this->postJson(route('support.ask'), ['question' => 'Bonjou'])
        ->assertStatus(503)
        ->assertJsonPath('error', __("Aucun fournisseur d'IA n'est configuré sur ce serveur."));
});

// Yon endpwen JSON andeyò `api/*` dwe reponn an JSON lè validasyon an
// echwe tou. Yon redireksyon HTML bay yon `fetch()` fè paj la tonbe an
// silans — se konsa yon bouton sispann travay san okenn mesaj.
it('refuses an empty question in JSON, not with a redirect', function () {
    $this->postJson(route('support.ask'), ['question' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question');
});

// Yon bwat chat piblik san limit se yon fakti ouvè. Apre yon seri echanj,
// nou voye moun nan kote yon moun ka reponn li toutbon.
it('hands a long conversation over to a human on WhatsApp', function () {
    useSupportProvider(new FakeChatProvider('demo', reply: 'Oke.'));

    for ($i = 0; $i < 12; $i++) {
        $this->postJson(route('support.ask'), ['question' => "kesyon {$i}"]);
    }

    $this->postJson(route('support.ask'), ['question' => 'youn anplis'])
        ->assertOk()
        ->assertJsonPath('closed', true);
});

// Sipò a pa dwe pwomèt anyen: se yon chatbot, li pa gen zouti ki aji.
it('is built on the chatbot model, so it has no action it could promise', function () {
    $agent = PlatformSupportAgent::definition();

    expect($agent->confirmation['always_confirm'])->toBe([])
        ->and($agent->hasTool('create_order'))->toBeFalse()
        ->and($agent->handoffTo)->toBe(PlatformSupportAgent::WHATSAPP)
        ->and($agent->compiledPrompt('ht'))->toContain('LOUVIA');
});
