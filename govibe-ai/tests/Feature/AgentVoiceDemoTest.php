<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\AIProvider\Connectors\ElevenLabs\ElevenLabsProvider;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Tests\Support\FakeChatProvider;

uses(RefreshDatabase::class);

/** @param list<object> $providers */
function useVoiceProviders(array $providers): void
{
    $registry = new ProviderRegistry;

    foreach ($providers as $provider) {
        $registry->register($provider);
    }

    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(ModelCatalog::class);
    app()->forgetInstance(AiRouter::class);
}

function voiceNote(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('vwa.webm', 'ODYO-BINÈ');
}

// ── Paj la ──────────────────────────────────────────────────────────────

it('opens on two ways in: call or chat', function () {
    $this->get(route('agents.demo', 'restaurant'))
        ->assertOk()
        ->assertSee(__('Appel'))
        ->assertSee(__('Chat'))
        ->assertSee(route('agents.demo', ['sector' => 'restaurant', 'mode' => 'call']));
});

// Yon kle vwa pou kont li pa vle di ajan an ka reponn. Si paj la te di
// « tout bon », chak mesaj ta tonbe san esplikasyon.
it('still warns that no AI key is configured when only the voice key is', function () {
    useVoiceProviders([new ElevenLabsProvider(['api_key' => 'xi-test'])]);

    $this->get(route('agents.index'))
        ->assertOk()
        ->assertSee(__("Aucune clé d'IA n'est configurée sur ce serveur."));
});

it('says plainly that voice is off, instead of a call button that stays silent', function () {
    useVoiceProviders([new FakeChatProvider('demo')]);

    $this->get(route('agents.demo', ['sector' => 'restaurant', 'mode' => 'call']))
        ->assertOk()
        ->assertSee(__("La voix n'est pas encore activée sur ce serveur : l'agent répondra par écrit. Ajoutez une clé ElevenLabs pour l'entendre."));
});

// ── Apèl la ─────────────────────────────────────────────────────────────

it('hears a voice note, answers it, and speaks the answer back', function () {
    Http::fake([
        'api.elevenlabs.io/v1/speech-to-text' => Http::response(['text' => 'Konbyen griyo a?', 'language_code' => 'ht']),
        'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('AUDIO-BINÈ', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    useVoiceProviders([
        new FakeChatProvider('demo', reply: 'Griyo a 500 goud.'),
        new ElevenLabsProvider(['api_key' => 'xi-test']),
    ]);

    $response = $this->post(route('agents.demo.voice', 'restaurant'), [
        'audio' => voiceNote(),
        'speak' => '1',
    ])->assertOk();

    expect($response->json('transcript'))->toBe('Konbyen griyo a?')
        ->and($response->json('reply'))->toBe('Griyo a 500 goud.')
        ->and($response->json('audio'))->toStartWith('data:audio/mpeg;base64,');
});

// Yon apèl san son pi bon pase yon apèl san repons.
it('still answers in writing when the voice provider is missing', function () {
    useVoiceProviders([new FakeChatProvider('demo', reply: 'Nou louvri 10h-22h.')]);

    $response = $this->post(route('agents.demo.voice', 'restaurant'), [
        'text' => 'Ki lè nou louvri?',
        'speak' => '1',
    ])->assertOk();

    expect($response->json('reply'))->toBe('Nou louvri 10h-22h.')
        ->and($response->json('audio'))->toBeNull();
});

// Nan mòd chat, yon mesaj ekri pa dwe boule kredi vwa.
it('does not synthesise speech when the caller did not ask for it', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response('AUDIO', 200)]);

    useVoiceProviders([
        new FakeChatProvider('demo', reply: 'Oke.'),
        new ElevenLabsProvider(['api_key' => 'xi-test']),
    ]);

    $this->post(route('agents.demo.voice', 'restaurant'), ['text' => 'Bonjou'])->assertOk();

    Http::assertNothingSent();
});

it('keeps the memory of a spoken exchange, exactly like a written one', function () {
    useVoiceProviders([new FakeChatProvider('demo', reply: 'Ak ki akonpayman?')]);

    $this->post(route('agents.demo.voice', 'restaurant'), ['text' => 'Mwen vle de griyo'])->assertOk();

    // Menm konvèsasyon: paj la (mòd tèks) dwe montre sa ki te di alavwa.
    $this->get(route('agents.demo', 'restaurant'))
        ->assertOk()
        ->assertSee('Mwen vle de griyo');
});

it('refuses a call that carries neither speech nor text', function () {
    useVoiceProviders([new FakeChatProvider('demo')]);

    $this->postJson(route('agents.demo.voice', 'restaurant'), [])
        ->assertStatus(422)
        ->assertJsonPath('error', __("Rien n'a été entendu. Réessayez."));
});

it('says the transcription is unavailable instead of answering a guess', function () {
    useVoiceProviders([new FakeChatProvider('demo', configured: false)]);

    $this->post(route('agents.demo.voice', 'restaurant'), ['audio' => voiceNote()])
        ->assertStatus(503)
        ->assertJsonPath('error', __("La transcription vocale n'est pas disponible sur ce serveur."));
});
