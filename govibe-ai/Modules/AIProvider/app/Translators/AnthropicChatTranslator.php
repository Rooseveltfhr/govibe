<?php

namespace Modules\AIProvider\Translators;

use JsonException;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\StreamChunk;
use Modules\AIProvider\DTO\TokenUsage;

/**
 * Traduction vers/depuis l'API Messages d'Anthropic : les messages « system »
 * sont hissés dans un champ dédié et `max_tokens` est obligatoire.
 *
 * Classe pure : aucune E/S.
 */
class AnthropicChatTranslator
{
    public const DEFAULT_MAX_TOKENS = 4096;

    /** @return array<string, mixed> */
    public function buildPayload(ChatRequest $request, string $model): array
    {
        $system = [];
        $messages = [];

        foreach ($request->messages as $message) {
            if ($message->role === 'system') {
                $system[] = $message->content;

                continue;
            }

            $messages[] = [
                // Anthropic ne connaît que « user » et « assistant ».
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $message->content,
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages !== [] ? $messages : [['role' => 'user', 'content' => '']],
            'max_tokens' => $request->maxTokens ?? self::DEFAULT_MAX_TOKENS,
        ];

        if ($system !== []) {
            $payload['system'] = implode("\n\n", $system);
        }

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->stream) {
            $payload['stream'] = true;
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    public function parseResponse(array $data, string $providerKey, string $model): ChatResponse
    {
        $blocks = is_array($data['content'] ?? null) ? $data['content'] : [];
        $text = '';

        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $stopReason = $data['stop_reason'] ?? 'stop';

        return new ChatResponse(
            content: $text,
            model: is_string($data['model'] ?? null) ? $data['model'] : $model,
            providerKey: $providerKey,
            usage: new TokenUsage(
                (int) ($usage['input_tokens'] ?? 0),
                (int) ($usage['output_tokens'] ?? 0),
            ),
            finishReason: $this->normalizeStopReason(is_string($stopReason) ? $stopReason : 'stop'),
            externalId: is_string($data['id'] ?? null) ? $data['id'] : null,
        );
    }

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

        $type = is_string($data['type'] ?? null) ? $data['type'] : '';

        if ($type === 'content_block_delta') {
            $delta = is_array($data['delta'] ?? null) ? $data['delta'] : [];
            $text = $delta['text'] ?? '';

            return is_string($text) && $text !== '' ? new StreamChunk($text) : null;
        }

        if ($type === 'message_delta') {
            $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
            $delta = is_array($data['delta'] ?? null) ? $data['delta'] : [];
            $stopReason = $delta['stop_reason'] ?? 'stop';

            return StreamChunk::end(
                new TokenUsage(
                    (int) ($usage['input_tokens'] ?? 0),
                    (int) ($usage['output_tokens'] ?? 0),
                ),
                $this->normalizeStopReason(is_string($stopReason) ? $stopReason : 'stop'),
            );
        }

        if ($type === 'message_stop') {
            return StreamChunk::end();
        }

        return null;
    }

    /** Aligne les raisons d'arrêt sur le vocabulaire OpenAI exposé par notre API. */
    protected function normalizeStopReason(string $reason): string
    {
        return match ($reason) {
            'max_tokens' => 'length',
            'end_turn', 'stop_sequence' => 'stop',
            default => $reason,
        };
    }
}
