<?php

use Modules\AIProvider\DTO\ChatMessage;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\Translators\AnthropicChatTranslator;
use Modules\AIProvider\Translators\GeminiChatTranslator;
use Modules\AIProvider\Translators\OpenAiChatTranslator;

function chatRequest(bool $stream = false): ChatRequest
{
    return new ChatRequest(
        messages: [
            new ChatMessage('system', 'Ou se yon asistan'),
            new ChatMessage('user', 'Bonjou'),
        ],
        model: 'un-modele',
        temperature: 0.4,
        maxTokens: 256,
        stream: $stream,
    );
}

describe('dialecte OpenAI', function () {
    it('builds a payload with the normalised messages', function () {
        $payload = (new OpenAiChatTranslator)->buildPayload(chatRequest(), 'gpt-4o-mini');

        expect($payload['model'])->toBe('gpt-4o-mini')
            ->and($payload['messages'])->toHaveCount(2)
            ->and($payload['messages'][0]['role'])->toBe('system')
            ->and($payload['temperature'])->toBe(0.4)
            ->and($payload['max_tokens'])->toBe(256)
            ->and($payload)->not->toHaveKey('stream');
    });

    it('asks for usage when streaming', function () {
        $payload = (new OpenAiChatTranslator)->buildPayload(chatRequest(stream: true), 'gpt-4o-mini');

        expect($payload['stream'])->toBeTrue()
            ->and($payload['stream_options'])->toBe(['include_usage' => true]);
    });

    it('parses a completion', function () {
        $response = (new OpenAiChatTranslator)->parseResponse([
            'id' => 'chatcmpl-1',
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => 'Bonjou!'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
        ], 'openai', 'gpt-4o-mini');

        expect($response->content)->toBe('Bonjou!')
            ->and($response->usage->inputTokens)->toBe(10)
            ->and($response->usage->outputTokens)->toBe(4)
            ->and($response->providerKey)->toBe('openai');
    });

    it('survives a malformed response', function () {
        $response = (new OpenAiChatTranslator)->parseResponse([], 'openai', 'gpt-4o-mini');

        expect($response->content)->toBe('')
            ->and($response->usage->total())->toBe(0);
    });

    it('parses stream lines', function () {
        $translator = new OpenAiChatTranslator;

        expect($translator->parseStreamLine('data: {"choices":[{"delta":{"content":"Bon"}}]}')?->delta)->toBe('Bon')
            ->and($translator->parseStreamLine('data: [DONE]')?->done)->toBeTrue()
            ->and($translator->parseStreamLine(''))->toBeNull()
            ->and($translator->parseStreamLine(': keep-alive'))->toBeNull()
            ->and($translator->parseStreamLine('data: {invalide'))->toBeNull();
    });

    it('closes the stream on a finish reason', function () {
        $chunk = (new OpenAiChatTranslator)->parseStreamLine(
            'data: {"choices":[{"delta":{},"finish_reason":"length"}],"usage":{"prompt_tokens":5,"completion_tokens":2}}'
        );

        expect($chunk?->done)->toBeTrue()
            ->and($chunk?->finishReason)->toBe('length')
            ->and($chunk?->usage?->outputTokens)->toBe(2);
    });
});

describe('dialecte Anthropic', function () {
    it('hoists system messages and always sets max_tokens', function () {
        $payload = (new AnthropicChatTranslator)->buildPayload(chatRequest(), 'claude-sonnet-5');

        expect($payload['system'])->toBe('Ou se yon asistan')
            ->and($payload['messages'])->toHaveCount(1)
            ->and($payload['messages'][0]['role'])->toBe('user')
            ->and($payload['max_tokens'])->toBe(256);
    });

    it('falls back to a default max_tokens', function () {
        $payload = (new AnthropicChatTranslator)->buildPayload(
            new ChatRequest([new ChatMessage('user', 'Alo')]),
            'claude-sonnet-5',
        );

        expect($payload['max_tokens'])->toBe(AnthropicChatTranslator::DEFAULT_MAX_TOKENS);
    });

    it('parses text blocks and normalises the stop reason', function () {
        $response = (new AnthropicChatTranslator)->parseResponse([
            'id' => 'msg_1',
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'text', 'text' => 'Bonjou'], ['type' => 'text', 'text' => '!']],
            'stop_reason' => 'max_tokens',
            'usage' => ['input_tokens' => 7, 'output_tokens' => 3],
        ], 'anthropic', 'claude-sonnet-5');

        expect($response->content)->toBe('Bonjou!')
            ->and($response->finishReason)->toBe('length')
            ->and($response->usage->inputTokens)->toBe(7);
    });

    it('parses stream events', function () {
        $translator = new AnthropicChatTranslator;

        expect($translator->parseStreamLine('data: {"type":"content_block_delta","delta":{"text":"Bon"}}')?->delta)->toBe('Bon')
            ->and($translator->parseStreamLine('data: {"type":"message_stop"}')?->done)->toBeTrue()
            ->and($translator->parseStreamLine('data: {"type":"ping"}'))->toBeNull();
    });
});

describe('dialecte Gemini', function () {
    it('maps roles and system instruction', function () {
        $payload = (new GeminiChatTranslator)->buildPayload(
            new ChatRequest([
                new ChatMessage('system', 'Consigne'),
                new ChatMessage('user', 'Alo'),
                new ChatMessage('assistant', 'Wi'),
            ]),
            'gemini-2.0-flash',
        );

        expect($payload['systemInstruction']['parts'][0]['text'])->toBe('Consigne')
            ->and($payload['contents'][0]['role'])->toBe('user')
            ->and($payload['contents'][1]['role'])->toBe('model');
    });

    it('parses a response and its usage', function () {
        $response = (new GeminiChatTranslator)->parseResponse([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Bonjou']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 9, 'candidatesTokenCount' => 2],
        ], 'gemini', 'gemini-2.0-flash');

        expect($response->content)->toBe('Bonjou')
            ->and($response->finishReason)->toBe('stop')
            ->and($response->usage->inputTokens)->toBe(9);
    });

    it('parses stream events', function () {
        $chunk = (new GeminiChatTranslator)->parseStreamLine(
            'data: {"candidates":[{"content":{"parts":[{"text":"Bon"}]}}]}'
        );

        expect($chunk?->delta)->toBe('Bon')->and($chunk?->done)->toBeFalse();
    });
});
