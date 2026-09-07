<?php

namespace Modules\AIProvider\Contracts;

use Modules\AIProvider\DTO\VoiceDescriptor;

/**
 * Yon founisè vwa ki kite w wè bibliyotèk kont lan epi ajoute pwòp vwa w.
 *
 * Apa de `SupportsSpeech`: yon founisè ka konnen pale san li pa kite w
 * anrejistre yon vwa nouvo. Se de kapasite, se de kontra.
 */
interface SupportsVoiceLibrary
{
    /** @return list<VoiceDescriptor> */
    public function voices(): array;

    /**
     * Ajoute yon vwa apati echantiyon odyo.
     *
     * @param  list<string>  $samplePaths  chemen fichye odyo sou disk
     */
    public function addVoice(string $name, array $samplePaths, ?string $description = null): VoiceDescriptor;
}
