<?php

namespace Modules\AIServices\Transcription;

use Modules\AIProvider\Contracts\SupportsTranscription;
use Modules\AIProvider\DTO\TranscriptionRequest;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Transkri yon fichye odyo an tèks — premye founisè konfigire ki sipòte
 * transkripsyon an pran l. Pa gen skò/failover elabore isit la (jan
 * AiRouter fè pou chat): jodi a se yon sèl founisè (Whisper), sa ase.
 */
class TranscriptionService
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    /** @return array{text: string, provider: string, language: ?string} */
    public function transcribe(string $audioPath, ?string $language = null): array
    {
        foreach ($this->providers->configured() as $provider) {
            if (! $provider instanceof SupportsTranscription) {
                continue;
            }

            $response = $provider->transcribe(new TranscriptionRequest($audioPath, $language));

            return [
                'text' => $response->text,
                'provider' => $response->providerKey,
                'language' => $response->language,
            ];
        }

        throw new NoProviderAvailableException('Pa gen okenn founisè transkripsyon (vwa→tèks) konfigire.');
    }
}
