<?php

namespace Modules\AIProvider\Translators;

use JsonException;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\StreamChunk;
use Modules\AIProvider\DTO\TokenUsage;

/**
 * Traduction vers/depuis l'API Generative Language de Google : les messages
 * deviennent des « contents » à parties, le rôle assistant s'appelle « model »
 * et les consignes système vivent dans `systemInstruction`.
 *
 * Classe pure : aucune E/S.
 */
class GeminiChatTranslator
{
    /** @return array<string, mixed> */
    public function buildPayload(ChatRequest $request, string $model): array
    {
        $system = [];
        $contents = [];

        foreach ($request->messages as $message) {
            if ($message->role === 'system') {
                $system[] = $message->content;

                continue;
            }

            $contents[] = [
                'role' => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ];
        }

        $payload = ['contents' => $contents];

        if ($system !== []) {
            $payload['systemInstruction'] = ['parts' => [['text' => implode("\n\n", $system)]]];
        }

        $generationConfig = [];

        if ($request->temperature !== null) {
            $generationConfig['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $request->maxTokens;
        }

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    public function parseResponse(array $data, string $providerKey, string $model): ChatResponse
    {
        $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];
        $first = is_array($candidates[0] ?? null) ? $candidates[0] : [];

        $text = $this->extractText($first);
        $finishReason = $first['finishReason'] ?? 'STOP';
        $usage = is_array($data['usageMetadata'] ?? null) ? $data['usageMetadata'] : [];

        return new ChatResponse(
            content: $text,
            model: $model,
            providerKey: $providerKey,
            usage: new TokenUsage(
                (int) ($usage['promptTokenCount'] ?? 0),
                (int) ($usage['candidatesTokenCount'] ?? 0),
            ),
            finishReason: $this->normalizeFinishReason(is_string($finishReason) ? $finishReason : 'STOP'),
        );
    }

    /**
     * Gemini diffuse un flux SSE dont chaque événement porte une réponse
     * partielle complète (mêmes champs que la réponse non diffusée).
     */
    public function parseStreamLine(string $line): ?StreamChunk
    {
        $line = trim($line);

        if ($line === '' || ! str_starts_with($line, 'data:')) {
            return null;
        }

        $payload = trim(substr($line, 5));

        if ($payload === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];
        $first = is_array($candidates[0] ?? null) ? $candidates[0] : [];

        $text = $this->extractText($first);
        $usageMeta = is_array($data['usageMetadata'] ?? null) ? $data['usageMetadata'] : null;
        $usage = $usageMeta === null ? null : new TokenUsage(
            (int) ($usageMeta['promptTokenCount'] ?? 0),
            (int) ($usageMeta['candidatesTokenCount'] ?? 0),
        );

        $finishReason = $first['finishReason'] ?? null;

        if (is_string($finishReason) && $finishReason !== '') {
            return new StreamChunk($text, true, $usage, $this->normalizeFinishReason($finishReason));
        }

        return $text === '' ? null : new StreamChunk($text, false, $usage);
    }

    /** @param array<string, mixed> $candidate */
    protected function extractText(array $candidate): string
    {
        $content = is_array($candidate['content'] ?? null) ? $candidate['content'] : [];
        $parts = is_array($content['parts'] ?? null) ? $content['parts'] : [];
        $text = '';

        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    protected function normalizeFinishReason(string $reason): string
    {
        return match ($reason) {
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY', 'RECITATION' => 'content_filter',
            default => strtolower($reason),
        };
    }
}
