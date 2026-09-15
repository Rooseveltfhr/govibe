<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Agents\Models\Agent;
use Modules\AIProvider\Connectors\ElevenLabs\ElevenLabsProvider;
use Modules\AIProvider\Models\VoiceProfile;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIServices\Speech\SpeechService;
use Modules\AIServices\Speech\VoiceLibrary;

uses(RefreshDatabase::class);

function voiceAdmin(): User
{
    return User::firstOrCreate(
        ['email' => 'admin@govibe.ht'],
        ['name' => 'Roosevelt', 'password' => 'yon-modpas-long-anpil', 'is_admin' => true],
    );
}

function useProviderVoices(bool $withKey = true, bool $withFakes = true): void
{
    $registry = new ProviderRegistry;
    $registry->register(new ElevenLabsProvider(['api_key' => $withKey ? 'xi-test' : '']));
    app()->instance(ProviderRegistry::class, $registry);
    app()->forgetInstance(VoiceLibrary::class);
    app()->forgetInstance(SpeechService::class);

    if (! $withFakes) {
        return;
    }

    Http::fake([
        'api.elevenlabs.io/v1/voices' => Http::response(['voices' => [
            ['voice_id' => 'pre-1', 'name' => 'Charlotte', 'category' => 'premade', 'preview_url' => 'https://x/1.mp3'],
            ['voice_id' => 'pre-2', 'name' => 'Daniel', 'category' => 'premade'],
        ]]),
        'api.elevenlabs.io/v1/voices/add' => Http::response(['voice_id' => 'kreyol-1']),
        'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('AUDIO', 200, ['Content-Type' => 'audio/mpeg']),
    ]);
}

// ── Pòt la ───────────────────────────────────────────────────────────────

it('keeps the voice library behind the admin login', function () {
    $this->get(route('admin.voices'))->assertRedirect(route('admin.login'));
});

// ── Pran yon vwa founisè a genyen ────────────────────────────────────────

it('shows what the provider has, apart from what we already took', function () {
    useProviderVoices();

    VoiceProfile::create(['voice_id' => 'pre-1', 'name' => 'Charlotte', 'language' => 'fr']);

    $this->actingAs(voiceAdmin())
        ->get(route('admin.voices'))
        ->assertOk()
        ->assertSee('Charlotte')   // nan bibliyotèk nou
        ->assertSee('Daniel');     // toujou disponib lakay founisè a

    expect(VoiceProfile::count())->toBe(1);
});

it('takes a provider voice into the library with its language', function () {
    useProviderVoices();

    $this->actingAs(voiceAdmin())->post(route('admin.voices.store'), [
        'voice_id' => 'pre-2',
        'name' => 'Daniel',
        'language' => 'fr',
    ])->assertRedirect();

    $voice = VoiceProfile::firstOrFail();

    expect($voice->voice_id)->toBe('pre-2')
        ->and($voice->language)->toBe('fr')
        ->and($voice->source)->toBe(VoiceProfile::SOURCE_LIBRARY);
});

// Yon vwa ki pa egziste lakay founisè a ta bay yon erè sou CHAK repons.
it('refuses a voice the provider does not have', function () {
    useProviderVoices();

    $this->actingAs(voiceAdmin())->post(route('admin.voices.store'), [
        'voice_id' => 'pa-egziste', 'name' => 'X', 'language' => 'fr',
    ])->assertSessionHasErrors('voice_id');

    expect(VoiceProfile::count())->toBe(0);
});

it('refuses a language that is not one of ours', function () {
    useProviderVoices();

    $this->actingAs(voiceAdmin())->post(route('admin.voices.store'), [
        'voice_id' => 'pre-1', 'name' => 'Charlotte', 'language' => 'de',
    ])->assertSessionHasErrors('language');
});

it('takes the same voice only once', function () {
    useProviderVoices();

    foreach (['Charlotte', 'Charlotte ankò'] as $name) {
        $this->actingAs(voiceAdmin())->post(route('admin.voices.store'), [
            'voice_id' => 'pre-1', 'name' => $name, 'language' => 'fr',
        ]);
    }

    expect(VoiceProfile::count())->toBe(1)
        ->and(VoiceProfile::first()?->name)->toBe('Charlotte ankò');
});

// ── Vwa pa defo pa lang ──────────────────────────────────────────────────

// Yon sèl vwa pa defo pa lang: si de vwa te make defo pou kreyòl, ajan an
// t ap pran youn nan de a san nou konnen kilès.
it('keeps a single default per language', function () {
    $first = VoiceProfile::create(['voice_id' => 'a', 'name' => 'A', 'language' => 'ht', 'is_default' => true]);
    $second = VoiceProfile::create(['voice_id' => 'b', 'name' => 'B', 'language' => 'ht']);
    $french = VoiceProfile::create(['voice_id' => 'c', 'name' => 'C', 'language' => 'fr', 'is_default' => true]);

    $second->makeDefault();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        // Lang franse a pa touche: se pa menm chwa.
        ->and($french->fresh()->is_default)->toBeTrue();
});

it('sets the default from the admin page', function () {
    useProviderVoices();

    $voice = VoiceProfile::create(['voice_id' => 'a', 'name' => 'A', 'language' => 'ht']);

    $this->actingAs(voiceAdmin())->post(route('admin.voices.update', $voice), [
        'name' => 'Vwa Kreyòl',
        'language' => 'ht',
        'is_default' => '1',
    ])->assertRedirect();

    expect($voice->fresh()->name)->toBe('Vwa Kreyòl')
        ->and($voice->fresh()->is_default)->toBeTrue();
});

it('removes a voice from our library without destroying it at the provider', function () {
    useProviderVoices();

    $voice = VoiceProfile::create(['voice_id' => 'pre-1', 'name' => 'A', 'language' => 'fr']);

    $this->actingAs(voiceAdmin())->delete(route('admin.voices.destroy', $voice))->assertRedirect();

    expect(VoiceProfile::count())->toBe(0);

    // Okenn apèl efase lakay founisè a: yon vwa klonaj se travay yon moun.
    Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE');
});

// ── Vwa kreyòl (klonaj) ──────────────────────────────────────────────────

// ElevenLabs pa livre yon vwa kreyòl. Se chemen sa a ki bay youn.
it('records a Creole voice and puts it in the library', function () {
    useProviderVoices();

    $this->actingAs(voiceAdmin())->post(route('admin.voices.clone'), [
        'name' => 'Vwa Kreyòl Ayiti',
        'language' => 'ht',
        'samples' => [UploadedFile::fake()->createWithContent('vwa.mp3', 'ODYO')],
    ])->assertRedirect();

    $voice = VoiceProfile::firstOrFail();

    expect($voice->voice_id)->toBe('kreyol-1')
        ->and($voice->language)->toBe('ht')
        ->and($voice->isCloned())->toBeTrue();
});

it('says so instead of pretending, when the provider refuses the recording', function () {
    // Pa gen fake pa defo isit la: yon dezyèm `Http::fake` pa ranplase premye
    // a, li vin apre l — epi règ ki pi presi a t ap toujou genyen.
    useProviderVoices(withFakes: false);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'quota'], 401)]);

    $this->actingAs(voiceAdmin())->post(route('admin.voices.clone'), [
        'name' => 'Vwa', 'language' => 'ht',
        'samples' => [UploadedFile::fake()->createWithContent('vwa.mp3', 'ODYO')],
    ])->assertSessionHasErrors('samples');

    expect(VoiceProfile::count())->toBe(0);
});

it('tells the admin when no Creole voice is set', function () {
    useProviderVoices();

    $this->actingAs(voiceAdmin())
        ->get(route('admin.voices'))
        ->assertOk()
        ->assertSee(__("Aucune voix marquée pour le créole. ElevenLabs n'en livre pas : enregistrez-en une plus bas avec des échantillons d'une personne qui parle créole."));
});

// ── Sa ki fè tout sa itil: ajan an pale ak vwa lang li a ─────────────────

// Se tès la ki konte: yon bibliyotèk ki pa chanje ki vwa ki pale se yon
// lis, se pa yon fonksyon.
it('speaks with the default voice of the language when the agent has none', function () {
    useProviderVoices();

    VoiceProfile::create(['voice_id' => 'kreyol-1', 'name' => 'Vwa Kreyòl', 'language' => 'ht', 'is_default' => true]);
    VoiceProfile::create(['voice_id' => 'pre-1', 'name' => 'Charlotte', 'language' => 'fr', 'is_default' => true]);

    app(SpeechService::class)->speak('Bonjou', 'ht');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/text-to-speech/kreyol-1'));
});

it('lets the agent voice win over the language default', function () {
    useProviderVoices();

    VoiceProfile::create(['voice_id' => 'kreyol-1', 'name' => 'Vwa Kreyòl', 'language' => 'ht', 'is_default' => true]);

    app(SpeechService::class)->speak('Bonjou', 'ht', 'vwa-pa-ajan-an');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/text-to-speech/vwa-pa-ajan-an'));
});

// Yon pann kay yon tyès pa dwe anpeche yon machann chanje vwa ajan l.
it('still accepts a curated voice when the provider list is unreachable', function () {
    useProviderVoices(withFakes: false);
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'down'], 500)]);

    VoiceProfile::create(['voice_id' => 'kreyol-1', 'name' => 'Vwa Kreyòl', 'language' => 'ht']);

    $agent = Agent::create([
        'key' => 'ti-kafe', 'name' => 'Ti Kafe', 'sector' => 'restaurant',
    ]);

    $this->post(route('agents.voice.update', $agent), ['voice_id' => 'kreyol-1'])
        ->assertRedirect();

    expect($agent->fresh()->voice_id)->toBe('kreyol-1');
});
