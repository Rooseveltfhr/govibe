<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\AIProvider\Connectors\ElevenLabs\ElevenLabsProvider;
use Modules\AIProvider\DTO\SpeechRequest;
use Modules\AIProvider\DTO\TranscriptionRequest;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Exceptions\ProviderException;

function elevenLabs(array $config = []): ElevenLabsProvider
{
    return new ElevenLabsProvider(array_merge(['api_key' => 'xi-test'], $config));
}

// Sa a se règ ki anpeche yon melanj: ElevenLabs pale, li pa panse. Si li ta
// deklare 'chat', AiRouter ta ka voye yon mesaj kliyan ba li.
it('offers voice and transcription but never chat', function () {
    $capabilities = elevenLabs()->capabilities();

    expect($capabilities)->toContain(Capability::Speech)
        ->and($capabilities)->toContain(Capability::Transcription)
        ->and($capabilities)->not->toContain(Capability::Chat);
});

it('is not configured without a key', function () {
    expect(elevenLabs(['api_key' => ''])->isConfigured())->toBeFalse()
        ->and(elevenLabs()->isConfigured())->toBeTrue();
});

it('turns text into audio with the documented endpoint and header', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response('AUDIO-BINÈ', 200, ['Content-Type' => 'audio/mpeg'])]);

    $response = elevenLabs(['voice_id' => 'vwa-tenan'])->speak(new SpeechRequest('Bonjou, byenveni.'));

    expect($response->audio)->toBe('AUDIO-BINÈ')
        ->and($response->providerKey)->toBe('elevenlabs')
        ->and($response->voice)->toBe('vwa-tenan')
        ->and($response->toDataUri())->toStartWith('data:audio/mpeg;base64,');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/vwa-tenan'
            && $request->hasHeader('xi-api-key', 'xi-test')
            && $request['text'] === 'Bonjou, byenveni.'
            && $request['model_id'] === ElevenLabsProvider::DEFAULT_SPEECH_MODEL;
    });
});

it('falls back to the default voice when the tenant has not chosen one', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response('AUDIO', 200)]);

    elevenLabs()->speak(new SpeechRequest('Alo'));

    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/text-to-speech/'.ElevenLabsProvider::DEFAULT_VOICE);
    });
});

it('reads back the transcript and the language ElevenLabs detected', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response(['text' => 'Mwen vle de griyo', 'language_code' => 'ht'])]);

    $path = tempnam(sys_get_temp_dir(), 'vwa');
    file_put_contents((string) $path, 'ODYO');

    $response = elevenLabs()->transcribe(new TranscriptionRequest((string) $path));

    expect($response->text)->toBe('Mwen vle de griyo')
        ->and($response->language)->toBe('ht')
        ->and($response->providerKey)->toBe('elevenlabs');

    unlink((string) $path);
});

// Yon 401 dwe rive jiska kouch ki rele a kòm yon erè founisè, pa kòm yon
// repons vid ki ta pase pou yon siksè an silans.
it('raises a provider error when ElevenLabs refuses the key', function () {
    Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => 'invalid_api_key'], 401)]);

    expect(fn () => elevenLabs()->speak(new SpeechRequest('Alo')))
        ->toThrow(ProviderException::class);
});
