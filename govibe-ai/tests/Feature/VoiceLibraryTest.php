<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Agents\Models\Agent;
use Modules\AIProvider\Connectors\ElevenLabs\ElevenLabsProvider;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Modules\AIServices\Speech\VoiceLibrary;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

function useVoiceLibrary(bool $withKey = true): void
{
    $registry = new ProviderRegistry;
    $registry->register(new ElevenLabsProvider(['api_key' => $withKey ? 'xi-test' : '']));
    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(VoiceLibrary::class);
}

function fakeVoicesResponse(): void
{
    Http::fake(['api.elevenlabs.io/v1/voices' => Http::response(['voices' => [
        ['voice_id' => 'pre-1', 'name' => 'Rachel', 'category' => 'premade', 'preview_url' => 'https://x/1.mp3', 'labels' => ['accent' => 'american']],
        ['voice_id' => 'mine-1', 'name' => 'Chita & Manje', 'category' => 'cloned', 'description' => 'Vwa mèt restoran an'],
        ['name' => 'san id'],  // dwe inyore, san kraze lis la
    ]])]);
}

function agentWithVoice(?string $voiceId = null): Agent
{
    return Agent::create([
        'key' => 'ti-kafe', 'name' => 'Ti Kafe', 'sector' => 'restaurant', 'voice_id' => $voiceId,
    ]);
}

// ── Bibliyotèk la ────────────────────────────────────────────────────────

it('lists the account voices and puts the merchant’s own first', function () {
    useVoiceLibrary();
    fakeVoicesResponse();

    $voices = app(VoiceLibrary::class)->all();

    expect($voices)->toHaveCount(2)                 // liy san voice_id la inyore
        ->and($voices[0]->id)->toBe('mine-1')       // vwa pa l an premye
        ->and($voices[0]->isMine())->toBeTrue()
        ->and($voices[1]->name)->toBe('Rachel')
        ->and($voices[1]->labels)->toBe(['accent' => 'american']);

    Http::assertSent(fn (Request $r): bool => $r->hasHeader('xi-api-key', 'xi-test'));
});

// Paj « chwazi yon vwa » a pa dwe bay yon 500 paske yon API andeyò tonbe.
it('returns an empty library instead of failing when the provider is down', function () {
    useVoiceLibrary();
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'boom'], 500)]);

    expect(app(VoiceLibrary::class)->all())->toBe([]);
});

it('has no library at all without a key', function () {
    useVoiceLibrary(withKey: false);

    expect(app(VoiceLibrary::class)->available())->toBeFalse()
        ->and(app(VoiceLibrary::class)->all())->toBe([]);
});

// ── Chwazi yon vwa ───────────────────────────────────────────────────────

it('shows the voices a merchant can choose for an agent', function () {
    useVoiceLibrary();
    fakeVoicesResponse();

    $agent = agentWithVoice();

    $this->get(route('agents.voice.edit', $agent))
        ->assertOk()
        ->assertSee('Chita &amp; Manje', false)
        ->assertSee('Rachel')
        ->assertSee(__('Voix par défaut'));
});

it('keeps the chosen voice on the agent', function () {
    useVoiceLibrary();
    fakeVoicesResponse();

    $agent = agentWithVoice();

    $this->post(route('agents.voice.update', $agent), ['voice_id' => 'mine-1'])->assertRedirect();

    expect($agent->fresh()->voice_id)->toBe('mine-1');
});

// Yon vwa ki pa egziste ta bay yon erè sou CHAK repons — epi machann nan
// t ap dekouvri sa nan yon apèl ak yon vrè kliyan.
it('refuses a voice that is not in the library', function () {
    useVoiceLibrary();
    fakeVoicesResponse();

    $agent = agentWithVoice('mine-1');

    $this->post(route('agents.voice.update', $agent), ['voice_id' => 'pa-egziste'])
        ->assertSessionHasErrors('voice_id');

    expect($agent->fresh()->voice_id)->toBe('mine-1');
});

it('falls back to the default voice when the choice is cleared', function () {
    useVoiceLibrary();
    fakeVoicesResponse();

    $agent = agentWithVoice('mine-1');

    $this->post(route('agents.voice.update', $agent), ['voice_id' => '']);

    expect($agent->fresh()->voice_id)->toBeNull();
});

// ── Anrejistre pwòp vwa w ────────────────────────────────────────────────

it('records a new voice and gives it to the agent straight away', function () {
    useVoiceLibrary();
    Http::fake([
        'api.elevenlabs.io/v1/voices/add' => Http::response(['voice_id' => 'nouvo-1']),
        'api.elevenlabs.io/v1/voices' => Http::response(['voices' => []]),
    ]);

    $agent = agentWithVoice();

    $this->post(route('agents.voice.store', $agent), [
        'name' => 'Vwa Ti Kafe',
        'samples' => [UploadedFile::fake()->createWithContent('vwa.mp3', 'ODYO')],
    ])->assertRedirect();

    expect($agent->fresh()->voice_id)->toBe('nouvo-1');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/voices/add'));
});

it('says so instead of pretending, when the voice cannot be recorded', function () {
    useVoiceLibrary();
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'quota'], 401)]);

    $agent = agentWithVoice();

    $this->post(route('agents.voice.store', $agent), [
        'name' => 'Vwa Ti Kafe',
        'samples' => [UploadedFile::fake()->createWithContent('vwa.mp3', 'ODYO')],
    ])->assertSessionHasErrors('samples');

    expect($agent->fresh()->voice_id)->toBeNull();
});

it('demands at least one sample', function () {
    useVoiceLibrary();

    $this->post(route('agents.voice.store', agentWithVoice()), ['name' => 'Vwa'])
        ->assertSessionHasErrors('samples');
});

// ── Vwa a rive jouk nan repons lan ───────────────────────────────────────

// Se sa ki konte: chwazi yon vwa ki pa chanje anyen nan apèl la se yon
// bouton ki bay yon santiman, pa yon fonksyon.
it('speaks the demo answer with the voice chosen for that agent', function () {
    $registry = new ProviderRegistry;
    $registry->register(new FakeChatProvider('demo', reply: 'Oke.'));
    $registry->register(new ElevenLabsProvider(['api_key' => 'xi-test']));
    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);

    Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::response('AUDIO', 200, ['Content-Type' => 'audio/mpeg'])]);

    $agent = agentWithVoice('mine-1');

    $this->post(route('agents.demo.voice', 'restaurant'), [
        'agent' => $agent->id, 'text' => 'Bonjou', 'speak' => '1',
    ])->assertOk();

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/text-to-speech/mine-1'));
});
