<?php

namespace Modules\AIServices\Speech;

use Modules\AIProvider\Contracts\SupportsVoiceLibrary;
use Modules\AIProvider\DTO\VoiceDescriptor;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Bibliyotèk vwa yo, jan entèfas la wè l.
 *
 * `all()` **tolerab**: san kle, oswa si founisè a tonbe, li retounen yon lis
 * vid. Rezon an: paj « chwazi yon vwa » a pa dwe bay yon 500 paske yon API
 * andeyò pa reponn — li dwe di « pa gen vwa disponib » epi kite rès paj la
 * mache. Men `add()` **pa** tolerab: si yon machann voye yon anrejistreman
 * epi li echwe, li dwe konnen sa touswit.
 */
class VoiceLibrary
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function available(): bool
    {
        return $this->provider() !== null;
    }

    /** @return list<VoiceDescriptor> */
    public function all(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return [];
        }

        try {
            $voices = $provider->voices();
        } catch (ProviderException) {
            return [];
        }

        // Vwa machann nan anrejistre yo an premye: se pa l, li p ap chèche
        // yo nan yon lis swasant vwa founisè a bay.
        usort($voices, static function (VoiceDescriptor $a, VoiceDescriptor $b): int {
            return [$b->isMine(), $a->name] <=> [$a->isMine(), $b->name];
        });

        return $voices;
    }

    public function find(?string $id): ?VoiceDescriptor
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($this->all() as $voice) {
            if ($voice->id === $id) {
                return $voice;
            }
        }

        return null;
    }

    /** @param list<string> $samplePaths */
    public function add(string $name, array $samplePaths, ?string $description = null): VoiceDescriptor
    {
        $provider = $this->provider();

        if ($provider === null) {
            throw new NoProviderAvailableException('Pa gen okenn founisè vwa konfigire.');
        }

        return $provider->addVoice($name, $samplePaths, $description);
    }

    private function provider(): ?SupportsVoiceLibrary
    {
        foreach ($this->providers->configured() as $provider) {
            if ($provider instanceof SupportsVoiceLibrary) {
                return $provider;
            }
        }

        return null;
    }
}
