<?php

namespace Modules\AIProvider\Translators;

use JsonException;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\StreamChunk;
use Modules\AIProvider\DTO\TokenUsage;

/**
 * Traduction vers/depuis le dialecte OpenAI (« /chat/completions »), partagé
 * par OpenAI, DeepSeek, OpenRouter, Mistral, Qwen, Groq, Together…
 *
 * Classe pure : aucune E/S, donc entièrement testable en unitaire.
 */
class OpenAiChatTranslator
{
    /** @return array<string, mixed> */
    public function buildPayload(ChatRequest $request, string $model): array
    {
        $payload = [
            'model' => $model,
            'messages' => $request->messagesToArray(),
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->stream) {
            $payload['stream'] = true;
            // Demande explicite des jetons en fin de flux (ignoré par les
            // fournisseurs qui ne le gèrent pas).
            $payload['stream_options'] = ['include_usage' => true];
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    public function parseResponse(array $data, string $providerKey, string $model): ChatResponse
    {
        $choices = is_array($data['choices'] ?? null) ? $data['choices'] : [];
        $first = is_array($choices[0] ?? null) ? $choices[0] : [];
        $message = is_array($first['message'] ?? null) ? $first['message'] : [];

        $content = $message['content'] ?? '';
        $finishReason = $first['finish_reason'] ?? 'stop';

        return new ChatResponse(
            content: is_string($content) ? $content : '',
            model: is_string($data['model'] ?? null) ? $data['model'] : $model,
            providerKey: $providerKey,
            usage: $this->parseUsage(is_array($data['usage'] ?? null) ? $data['usage'] : null) ?? new TokenUsage,
            finishReason: is_string($finishReason) ? $finishReason : 'stop',
            externalId: is_string($data['id'] ?? null) ? $data['id'] : null,
        );
    }

    /**
     * Traduit une ligne du flux SSE. Retourne null pour les lignes à ignorer
     * (commentaires de maintien de connexion, lignes vides, JSON illisible).
     */
    public function parseStreamLine(string $line): ?StreamChunk
    {
        $line = trim($line);

        if ($line === '' || ! str_starts_with($line, 'data:')) {
            return null;
        }

        $payload = trim(substr($line, 5));

        if ($payload === '' || $payload === '[DONE]') {
            return $payload === '[DONE]' ? StreamChunk::end() : null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $usage = $this->parseUsage(is_array($data['usage'] ?? null) ? $data['usage'] : null);

        $choices = is_array($data['choices'] ?? null) ? $data['choices'] : [];
        $first = is_array($choices[0] ?? null) ? $choices[0] : [];
        $delta = is_array($first['delta'] ?? null) ? $first['delta'] : [];
        $content = $delta['content'] ?? '';
        $finishReason = $first['finish_reason'] ?? null;

        if (is_string($finishReason) && $finishReason !== '') {
            return StreamChunk::end($usage, $finishReason);
        }

        // Certains fournisseurs envoient un dernier événement ne portant que l'usage.
        if (! is_string($content) || $content === '') {
            return $usage !== null ? new StreamChunk('', false, $usage) : null;
        }

        return new StreamChunk($content, false, $usage);
    }

    /** @param array<string, mixed>|null $usage */
    protected function parseUsage(?array $usage): ?TokenUsage
    {
        if ($usage === null) {
            return null;
        }

        return new TokenUsage(
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
        );
    }
}
