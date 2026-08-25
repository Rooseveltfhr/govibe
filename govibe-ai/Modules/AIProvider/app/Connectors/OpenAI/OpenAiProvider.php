<?php

namespace Modules\AIProvider\Connectors\OpenAI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\AIProvider\Connectors\OpenAiCompatibleProvider;
use Modules\AIProvider\Contracts\SupportsTranscription;
use Modules\AIProvider\DTO\TranscriptionRequest;
use Modules\AIProvider\DTO\TranscriptionResponse;
use Modules\AIProvider\Exceptions\ProviderException;

class OpenAiProvider extends OpenAiCompatibleProvider implements SupportsTranscription
{
    public function key(): string
    {
        return 'openai';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    /**
     * Transkripsyon Whisper. Se yon apèl multipart — pa menm fòma ak
     * chat() la, se poutèt sa li pa pase nan headers()/http() jenerik la
     * (sa a mande 'Content-Type: application/json').
     */
    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        $model = $request->model ?? 'whisper-1';

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($this->apiKey())
                ->timeout($this->timeout())
                ->connectTimeout(10)
                ->attach('file', file_get_contents($request->audioPath) ?: '', basename($request->audioPath))
                ->post('/audio/transcriptions', array_filter([
                    'model' => $model,
                    'language' => $request->language,
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
            language: $request->language,
        );
    }
}
