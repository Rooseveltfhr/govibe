<?php

namespace Modules\AIServices\Speech;

use Modules\AIProvider\Contracts\SupportsSpeech;
use Modules\AIProvider\DTO\SpeechRequest;
use Modules\AIProvider\DTO\SpeechResponse;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Tèks → vwa. Premye founisè konfigire ki konnen pale a pran l.
 *
 * Menm senplisite ak TranscriptionService: pa gen skò ni failover isit la.
 * Yon repons vokal ki pran de segond an plis pa vo anyen — si founisè a pa
 * la, apèl la tonbe sou tèks, li pa tann.
 */
class SpeechService
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function available(): bool
    {
        return $this->provider() !== null;
    }

    public function speak(string $text, ?string $language = null, ?string $voice = null): SpeechResponse
    {
        $provider = $this->provider();

        if ($provider === null) {
            throw new NoProviderAvailableException('Pa gen okenn founisè vwa (tèks→vwa) konfigire.');
        }

        return $provider->speak(new SpeechRequest(
            text: $text,
            voice: $voice,
            language: $language,
        ));
    }

    private function provider(): ?SupportsSpeech
    {
        foreach ($this->providers->configured() as $provider) {
            if ($provider instanceof SupportsSpeech) {
                return $provider;
            }
        }

        return null;
    }
}
