<?php

namespace Modules\AIServices\Chat;

use Generator;
use Illuminate\Support\Str;
use Modules\AIProvider\Contracts\AIProvider;
use Modules\AIProvider\Contracts\SupportsChat;
use Modules\AIProvider\DTO\ChatMessage;
use Modules\AIProvider\DTO\ChatRequest;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\DTO\StreamChunk;
use Modules\AIProvider\DTO\TokenUsage;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIRouter\DTO\RoutingContext;
use Modules\AIRouter\Events\AIRequestCompleted;
use Modules\AIRouter\Routing\AiRouter;
use Modules\AIRouter\Support\LanguageDetector;

/**
 * Service de conversation : construit le contexte de routage, délègue le choix
 * du fournisseur au routeur, puis publie l'événement de consommation.
 *
 * Le contrôleur ne fait que de la traduction HTTP ; toute la logique métier
 * du chat vit ici.
 */
class ChatService
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly LanguageDetector $detector,
    ) {}

    /**
     * @param  list<array{role?: string, content?: mixed}>  $messages
     * @return array{response: ChatResponse, request_id: string, provider: string, model: string}
     */
    public function complete(
        array $messages,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
        string $strategy = 'balanced',
    ): array {
        $request = $this->buildRequest($messages, $model, $temperature, $maxTokens, stream: false);
        $context = $this->buildContext($request, $model, $strategy);
        $uuid = (string) Str::uuid();

        try {
            $outcome = $this->router->execute(
                $context,
                fn (AIProvider $provider, ModelDescriptor $descriptor): ChatResponse => $this->callProvider(
                    $provider,
                    $descriptor,
                    $request,
                ),
            );
        } catch (\Throwable $e) {
            $this->recordFailure($uuid, $context, $e);

            throw $e;
        }

        /** @var ChatResponse $response */
        $response = $outcome['result'];
        $decision = $outcome['decision'];

        AIRequestCompleted::dispatch(
            $uuid,
            Capability::Chat->value,
            $decision->providerKey,
            $decision->modelKey,
            $decision->didFailover() ? 'failover' : 'ok',
            (int) $outcome['latency_ms'],
            $response->usage->inputTokens,
            $response->usage->outputTokens,
            $this->costOf($decision->providerKey, $decision->modelKey, $response->usage),
            $decision->attempts,
            false,
            $context->language,
            $decision->reason,
            null,
            ['failed_providers' => $decision->failedProviders],
        );

        return [
            'response' => $response,
            'request_id' => $uuid,
            'provider' => $decision->providerKey,
            'model' => $decision->modelKey,
        ];
    }

    /**
     * Diffusion incrémentale. La bascule reste possible tant qu'aucun octet
     * n'a été émis : le premier fragment est donc tiré *avant* que le routeur
     * ne considère la tentative comme réussie.
     *
     * @param  list<array{role?: string, content?: mixed}>  $messages
     * @return array{stream: Generator<int, StreamChunk>, request_id: string, provider: string, model: string}
     */
    public function stream(
        array $messages,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
        string $strategy = 'balanced',
    ): array {
        $request = $this->buildRequest($messages, $model, $temperature, $maxTokens, stream: true);
        $context = $this->buildContext($request, $model, $strategy);
        $uuid = (string) Str::uuid();

        try {
            $outcome = $this->router->execute($context, function (AIProvider $provider, ModelDescriptor $descriptor) use ($request): Generator {
                if (! $provider instanceof SupportsChat) {
                    throw new ProviderException(
                        sprintf('Le fournisseur %s ne gère pas la conversation.', $provider->key()),
                        $provider->key(),
                        retryable: true,
                    );
                }

                $generator = $provider->streamChat($request->withModel($descriptor->key));

                // Force l'ouverture du flux : une erreur HTTP surgit ici, donc
                // avant qu'un seul octet ne soit envoyé au client.
                $generator->rewind();

                return $generator;
            });
        } catch (\Throwable $e) {
            $this->recordFailure($uuid, $context, $e, streamed: true);

            throw $e;
        }

        /** @var Generator<int, StreamChunk> $generator */
        $generator = $outcome['result'];
        $decision = $outcome['decision'];

        return [
            'stream' => $this->wrapStream($generator, $uuid, $context, $decision->providerKey, $decision->modelKey, $decision->attempts, $decision->reason, (int) $outcome['latency_ms']),
            'request_id' => $uuid,
            'provider' => $decision->providerKey,
            'model' => $decision->modelKey,
        ];
    }

    /**
     * @param  Generator<int, StreamChunk>  $generator
     * @return Generator<int, StreamChunk>
     */
    private function wrapStream(
        Generator $generator,
        string $uuid,
        RoutingContext $context,
        string $providerKey,
        string $modelKey,
        int $attempts,
        string $reason,
        int $latencyMs,
    ): Generator {
        $usage = new TokenUsage;
        $characters = 0;

        foreach ($generator as $chunk) {
            $characters += mb_strlen($chunk->delta);

            if ($chunk->usage !== null) {
                $usage = $chunk->usage;
            }

            yield $chunk;
        }

        // Certains fournisseurs ne renvoient jamais l'usage en diffusion :
        // on estime alors la sortie pour ne pas facturer zéro.
        if ($usage->total() === 0) {
            $usage = new TokenUsage(
                $context->estimatedInputTokens,
                (int) max(1, ceil($characters / 4)),
            );
        }

        AIRequestCompleted::dispatch(
            $uuid,
            Capability::Chat->value,
            $providerKey,
            $modelKey,
            'ok',
            $latencyMs,
            $usage->inputTokens,
            $usage->outputTokens,
            $this->costOf($providerKey, $modelKey, $usage),
            $attempts,
            true,
            $context->language,
            $reason,
        );
    }

    private function callProvider(AIProvider $provider, ModelDescriptor $descriptor, ChatRequest $request): ChatResponse
    {
        if (! $provider instanceof SupportsChat) {
            throw new ProviderException(
                sprintf('Le fournisseur %s ne gère pas la conversation.', $provider->key()),
                $provider->key(),
                retryable: true,
            );
        }

        return $provider->chat($request->withModel($descriptor->key));
    }

    /** @param list<array{role?: string, content?: mixed}> $messages */
    private function buildRequest(
        array $messages,
        ?string $model,
        ?float $temperature,
        ?int $maxTokens,
        bool $stream,
    ): ChatRequest {
        $normalized = array_map(
            static fn (array $message): ChatMessage => ChatMessage::fromArray($message),
            $messages,
        );

        return new ChatRequest(
            messages: $normalized,
            model: $model,
            temperature: $temperature,
            maxTokens: $maxTokens,
            stream: $stream,
        );
    }

    private function buildContext(ChatRequest $request, ?string $model, string $strategy): RoutingContext
    {
        $text = implode(' ', array_map(
            static fn (ChatMessage $message): string => $message->content,
            $request->messages,
        ));

        return new RoutingContext(
            capability: Capability::Chat,
            strategy: $strategy,
            forcedModel: $model,
            language: $this->detector->detect($text),
            estimatedInputTokens: $request->estimatedInputTokens(),
            estimatedOutputTokens: $request->maxTokens ?? 512,
        );
    }

    private function costOf(string $providerKey, string $modelKey, TokenUsage $usage): int
    {
        $descriptor = $this->router->describe($providerKey, $modelKey);

        return $descriptor?->costMicro($usage->inputTokens, $usage->outputTokens) ?? 0;
    }

    private function recordFailure(string $uuid, RoutingContext $context, \Throwable $e, bool $streamed = false): void
    {
        AIRequestCompleted::dispatch(
            $uuid,
            $context->capability->value,
            'none',
            $context->forcedModel ?? 'auto',
            'failed',
            0,
            $context->estimatedInputTokens,
            0,
            0,
            1,
            $streamed,
            $context->language,
            null,
            class_basename($e),
            ['message' => mb_substr($e->getMessage(), 0, 500)],
        );
    }
}
