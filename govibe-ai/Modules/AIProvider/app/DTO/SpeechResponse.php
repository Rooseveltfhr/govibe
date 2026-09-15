<?php

namespace Modules\AIProvider\DTO;

/**
 * Odyo a tounen an memwa (binè), li pa ekri sou disk.
 *
 * Rezon an: yon repons vokal se yon bagay pou yon sèl kliyan, yon sèl fwa.
 * Ekri l nan yon dosye piblik ta vle di nenpòt moun ki devine URL la ka
 * koute konvèsasyon yon lòt moun. Kouch ki rele a deside sa pou fè avè l
 * (voye l an data URI, jete l apre).
 */
final readonly class SpeechResponse
{
    public function __construct(
        public string $audio,
        public string $providerKey,
        public string $mimeType = 'audio/mpeg',
        public ?string $voice = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->audio === '';
    }

    /** Fòm ki ka antre dirèkteman nan yon <audio src="..."> san yon URL piblik. */
    public function toDataUri(): string
    {
        return 'data:'.$this->mimeType.';base64,'.base64_encode($this->audio);
    }
}
