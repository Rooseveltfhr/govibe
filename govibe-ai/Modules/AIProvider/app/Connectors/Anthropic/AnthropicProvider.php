<?php

namespace Modules\AIProvider\Connectors\Anthropic;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Modules\AIProvider\Connectors\BaseProvider;
use Modules\AIProvider\Contracts\SupportsChat;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Translators\AnthropicChatTranslator;

class AnthropicProvider extends BaseProvider implements SupportsChat
{
    protected AnthropicChatTranslator $translator;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [], ?AnthropicChatTranslator $translator = null)
    {
        parent::__construct($config);

        $this->translator = $translator ?? new AnthropicChatTranslator;
    }

    public function key(): string
    {
        return 'anthropic';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $version = $this->config['version'] ?? null;

        return [
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => is_string($version) && $version !== '' ? $version : '2023-06-01',
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $model = $request->model ?? $this->defaultModel();
        $payload = $this->translator->buildPayload($request, $model);

        try {
            $response = $this->http()->post('/messages', $payload);
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

        return $this->translator->parseResponse($data, $this->key(), $model);
    }

    public function streamChat(ChatRequest $request): Generator
    {
        $model = $request->model ?? $this->defaultModel();
        $payload = $this->translator->buildPayload(
            new ChatRequest(
                $request->messages,
                $model,
                $request->temperature,
                $request->maxTokens,
                stream: true,
                language: $request->language,
            ),
            $model,
        );

        try {
            $response = $this->http()
                ->withOptions(['stream' => true])
                ->post('/messages', $payload);
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

        foreach ($this->streamLines($response->toPsrResponse()->getBody()) as $line) {
            $chunk = $this->translator->parseStreamLine($line);

            if ($chunk !== null) {
                yield $chunk;
            }
        }
    }

    public function defaultModel(): string
    {
        $configured = $this->config['default_model'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $models = $this->models();

        return $models === [] ? '' : $models[0]->key;
    }
}
