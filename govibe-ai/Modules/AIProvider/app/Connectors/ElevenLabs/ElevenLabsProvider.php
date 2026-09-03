<?php

namespace Modules\AIProvider\Connectors\ElevenLabs;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\AIProvider\Connectors\BaseProvider;
use Modules\AIProvider\Contracts\SupportsSpeech;
use Modules\AIProvider\Contracts\SupportsTranscription;
use Modules\AIProvider\DTO\SpeechRequest;
use Modules\AIProvider\DTO\SpeechResponse;
use Modules\AIProvider\DTO\TranscriptionRequest;
use Modules\AIProvider\DTO\TranscriptionResponse;
use Modules\AIProvider\Exceptions\ProviderException;

/**
 * ElevenLabs — vwa (tèks → odyo) ak transkripsyon (odyo → tèks).
 *
 * Li PA yon founisè chat: se konektè chat yo (OpenAI, Anthropic…) ki panse,
 * ElevenLabs ki pale. Se poutèt sa manifest la deklare sèlman kapasite
 * `speech` ak `transcription` — konsa AiRouter pa janm chwazi l pou reponn
 * yon mesaj.
 *
 * ⚠️ Konektè sa a ekri kont API ElevenLabs jan dokimantasyon piblik la
 * dekri l (`xi-api-key`, `/v1/text-to-speech/{voice_id}`, `/v1/speech-to-text`).
 * Li poko kouri kont yon vrè kle: se premye apèl ak yon kredansyèl reyèl ki
 * pral konfime chak chan. Si yon chan pa matche, se isit la pou korije l —
 * pa gen okenn lòt kote nan sistèm nan ki konnen fòma ElevenLabs.
 */
class ElevenLabsProvider extends BaseProvider implements SupportsSpeech, SupportsTranscription
{
    /** Vwa multileng ElevenLabs bay pa defo (« Rachel »). */
    public const DEFAULT_VOICE = '21m00Tcm4TlvDq8ikWAM';

    public const DEFAULT_SPEECH_MODEL = 'eleven_multilingual_v2';

    public const DEFAULT_TRANSCRIPTION_MODEL = 'scribe_v1';

    public function key(): string
    {
        return 'elevenlabs';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.elevenlabs.io/v1';
    }

    /**
     * ElevenLabs otantifye ak yon antèt `xi-api-key`, pa ak yon Bearer.
     *
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return ['xi-api-key' => $this->apiKey()];
    }

    /** Vwa pa defo pou tenan an, si li konfigire (chak biznis ka gen pa l). */
    public function defaultVoice(): string
    {
        $voice = $this->config['voice_id'] ?? '';

        return is_string($voice) && trim($voice) !== '' ? trim($voice) : self::DEFAULT_VOICE;
    }

    public function speak(SpeechRequest $request): SpeechResponse
    {
        $voice = $request->voice ?? $this->defaultVoice();

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders([
                    'xi-api-key' => $this->apiKey(),
                    'Accept' => 'audio/mpeg',
                ])
                ->timeout($this->timeout())
                ->connectTimeout(10)
                ->post('/text-to-speech/'.$voice, [
                    'text' => $request->text,
                    'model_id' => $request->model ?? self::DEFAULT_SPEECH_MODEL,
                ]);
        } catch (ConnectionException $e) {
            throw new ProviderException(
                sprintf('Connexion impossible à %s : %s', $this->key(), $e->getMessage()),
                $this->key(),
                true,
                null,
                $e,
            );
        }

        if ($response->failed()) {
            throw ProviderException::fromHttpStatus($this->key(), $response->status(), $response->body());
        }

        return new SpeechResponse(
            audio: $response->body(),
            providerKey: $this->key(),
            mimeType: $response->header('Content-Type') ?: 'audio/mpeg',
            voice: $voice,
        );
    }

    /**
     * Transkripsyon (Scribe). Menm remak ak Whisper: se yon apèl multipart,
     * kidonk li pa pase nan plonbri JSON jenerik la.
     */
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders(['xi-api-key' => $this->apiKey()])
                ->timeout($this->timeout())
                ->connectTimeout(10)
                ->attach('file', file_get_contents($request->audioPath) ?: '', basename($request->audioPath))
                ->post('/speech-to-text', array_filter([
                    'model_id' => $request->model ?? self::DEFAULT_TRANSCRIPTION_MODEL,
                    'language_code' => $request->language,
                ]));
        } catch (ConnectionException $e) {
            throw new ProviderException(
                sprintf('Connexion impossible à %s : %s', $this->key(), $e->getMessage()),
                $this->key(),
                true,
                null,
                $e,
            );
        }

        if ($response->failed()) {
            throw ProviderException::fromHttpStatus($this->key(), $response->status(), $response->body());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return new TranscriptionResponse(
            text: is_string($data['text'] ?? null) ? $data['text'] : '',
            providerKey: $this->key(),
            language: is_string($data['language_code'] ?? null) ? $data['language_code'] : $request->language,
        );
    }
}
