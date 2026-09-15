<?php

namespace Modules\AIServices\Chat\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AIProvider\DTO\ChatResponse;
use Modules\AIProvider\DTO\StreamChunk;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIServices\Chat\ChatService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Endpoint de conversation, **compatible avec le format OpenAI**.
 *
 * Décision structurante : tout SDK existant (Python, JS, PHP…) fonctionne avec
 * GOVIBE AI en changeant seulement `base_url` et la clé. C'est la voie
 * d'adoption la plus rapide pour une passerelle, et elle ne coûte qu'une
 * traduction de forme ici.
 */
class ChatCompletionsController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function __invoke(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['nullable', 'string', 'in:system,user,assistant,tool'],
            'messages.*.content' => ['required'],
            'model' => ['nullable', 'string', 'max:200'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:32000'],
            'stream' => ['nullable', 'boolean'],
            // Extension GOVIBE : oriente le routeur sans casser la compatibilité.
            'routing' => ['nullable', 'string', 'in:cost,speed,quality,balanced'],
        ]);

        /** @var list<array{role?: string, content?: mixed}> $messages */
        $messages = array_values($validated['messages']);
        $model = isset($validated['model']) && is_string($validated['model']) ? $validated['model'] : null;
        $temperature = isset($validated['temperature']) ? (float) $validated['temperature'] : null;
        $maxTokens = isset($validated['max_tokens']) ? (int) $validated['max_tokens'] : null;
        $strategy = isset($validated['routing']) && is_string($validated['routing']) ? $validated['routing'] : 'balanced';
        $stream = (bool) ($validated['stream'] ?? false);

        try {
            return $stream
                ? $this->streamed($messages, $model, $temperature, $maxTokens, $strategy)
                : $this->json($messages, $model, $temperature, $maxTokens, $strategy);
        } catch (NoProviderAvailableException $e) {
            return $this->error('no_provider_available', $e->getMessage(), 503);
        } catch (ProviderException $e) {
            return $this->error('provider_error', $e->getMessage(), $e->httpStatus ?? 502);
        } catch (Throwable $e) {
            report($e);

            return $this->error('internal_error', __('An unexpected error occurred.'), 500);
        }
    }

    /** @param list<array{role?: string, content?: mixed}> $messages */
    private function json(array $messages, ?string $model, ?float $temperature, ?int $maxTokens, string $strategy): JsonResponse
    {
        $outcome = $this->chat->complete($messages, $model, $temperature, $maxTokens, $strategy);

        /** @var ChatResponse $response */
        $response = $outcome['response'];

        return response()->json([
            'id' => 'chatcmpl-'.$outcome['request_id'],
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $outcome['provider'].'/'.$outcome['model'],
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $response->content],
                'finish_reason' => $response->finishReason,
            ]],
            'usage' => $response->usage->toOpenAiArray(),
            // Extension GOVIBE : traçabilité du routage.
            'govibe' => [
                'request_id' => $outcome['request_id'],
                'provider' => $outcome['provider'],
            ],
        ]);
    }

    /** @param list<array{role?: string, content?: mixed}> $messages */
    private function streamed(array $messages, ?string $model, ?float $temperature, ?int $maxTokens, string $strategy): StreamedResponse
    {
        $outcome = $this->chat->stream($messages, $model, $temperature, $maxTokens, $strategy);

        /** @var iterable<int, StreamChunk> $chunks */
        $chunks = $outcome['stream'];
        $id = 'chatcmpl-'.$outcome['request_id'];
        $modelId = $outcome['provider'].'/'.$outcome['model'];

        return response()->stream(function () use ($chunks, $id, $modelId): void {
            foreach ($chunks as $chunk) {
                if ($chunk->delta !== '') {
                    $this->emit([
                        'id' => $id,
                        'object' => 'chat.completion.chunk',
                        'created' => time(),
                        'model' => $modelId,
                        'choices' => [[
                            'index' => 0,
                            'delta' => ['content' => $chunk->delta],
                            'finish_reason' => null,
                        ]],
                    ]);
                }

                if ($chunk->done) {
                    $this->emit([
                        'id' => $id,
                        'object' => 'chat.completion.chunk',
                        'created' => time(),
                        'model' => $modelId,
                        'choices' => [[
                            'index' => 0,
                            'delta' => new \stdClass,
                            'finish_reason' => $chunk->finishReason ?? 'stop',
                        ]],
                    ]);
                }
            }

            echo "data: [DONE]\n\n";
            $this->flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            // Indispensable derrière nginx, sinon le flux est mis en tampon.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function emit(array $payload): void
    {
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
        $this->flush();
    }

    private function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'type' => $status >= 500 ? 'server_error' : 'invalid_request_error',
                'code' => $code,
                'message' => $message,
                'doc_url' => 'https://docs.govibe.ai/errors/'.$code,
            ],
        ], $status);
    }
}
