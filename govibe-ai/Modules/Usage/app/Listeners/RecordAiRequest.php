<?php

namespace Modules\Usage\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\AIRouter\Events\AIRequestCompleted;
use Modules\Usage\Models\AiRequest;
use Throwable;

/**
 * Journalise chaque appel IA dans `ai_requests`.
 *
 * Tolérant par construction : si l'écriture échoue, la réponse déjà servie au
 * client ne doit pas être compromise — on trace et on continue. La perte d'une
 * ligne de journal est préférable à une requête payante perdue.
 */
class RecordAiRequest
{
    public function handle(AIRequestCompleted $event): void
    {
        try {
            AiRequest::query()->create([
                'uuid' => $event->uuid,
                'capability' => $event->capability,
                'provider_key' => $event->providerKey,
                'model_key' => $event->modelKey,
                'status' => $event->status,
                'routed_reason' => $event->routedReason,
                'error_code' => $event->errorCode,
                'latency_ms' => $event->latencyMs,
                'input_tokens' => $event->inputTokens,
                'output_tokens' => $event->outputTokens,
                'cost_micro' => $event->costMicro,
                'attempts' => $event->attempts,
                'streamed' => $event->streamed,
                'language' => $event->language,
                'meta' => $event->meta,
            ]);
        } catch (Throwable $e) {
            Log::warning('Journalisation d’usage impossible', [
                'uuid' => $event->uuid,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
