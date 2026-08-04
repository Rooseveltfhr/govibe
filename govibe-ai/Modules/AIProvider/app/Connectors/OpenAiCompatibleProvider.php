<?php

namespace Modules\AIProvider\Connectors;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Modules\AIProvider\Contracts\SupportsChat;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Translators\OpenAiChatTranslator;

/**
 * Connecteur générique pour tout fournisseur exposant le dialecte OpenAI
 * (OpenAI, DeepSeek, OpenRouter, Mistral, Qwen, Groq, Together, vLLM…).
 *
 * Un nouveau fournisseur compatible se réduit alors à une sous-classe de
 * quelques lignes plus son manifeste.
 */
abstract class OpenAiCompatibleProvider extends BaseProvider implements SupportsChat
{
    protected OpenAiChatTranslator $translator;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [], ?OpenAiChatTranslator $translator = null)
    {
        parent::__construct($config);

        $this->translator = $translator ?? new OpenAiChatTranslator;
    }

    protected function chatEndpoint(): string
    {
        return '/chat/completions';
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $model = $request->model ?? $this->defaultModel();
        $payload = $this->translator->buildPayload($request, $model);

        try {
            $response = $this->http()->post($this->chatEndpoint(), $payload);
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
                ->post($this->chatEndpoint(), $payload);
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
