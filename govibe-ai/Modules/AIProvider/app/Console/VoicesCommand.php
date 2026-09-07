<?php

namespace Modules\AIProvider\Console;

use Illuminate\Console\Command;
use Modules\AIProvider\Contracts\SupportsVoiceLibrary;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Lis vwa kont lan — epi, an pasan, PREMYE tès reyèl konektè vwa a.
 *
 * Konektè ElevenLabs la ekri dapre dokimantasyon an; li poko janm rele yon
 * vrè API. Sandbox devlopman an bloke `elevenlabs.io`, kidonk verifikasyon
 * an pa ka fèt bò kote devlopè a: se sou sèvè a, ak vrè kle a, li dwe fèt.
 *
 * Kòmand sa a se chemen sa a. Li rele `GET /v1/voices` epi li di sa li
 * jwenn. Si repons lan pa gen fòm nou tann nan, nou wè sa nan jounal
 * deplwaman an — pa nan yon apèl ak yon kliyan.
 *
 * Li pa janm ekri yon kle.
 */
class VoicesCommand extends Command
{
    protected $signature = 'govibe:voices';

    protected $description = 'Montre vwa ki disponib yo (verifye konektè vwa a kont vrè API a)';

    public function handle(ProviderRegistry $registry): int
    {
        $provider = null;

        foreach ($registry->configured() as $candidate) {
            if ($candidate instanceof SupportsVoiceLibrary) {
                $provider = $candidate;
                break;
            }
        }

        if ($provider === null) {
            $this->warn('Pa gen okenn founisè vwa konfigire.');
            $this->line('Ajoute yon kle nan .env (egzanp: ELEVENLABS_API_KEY=...) epi relanse.');

            return self::FAILURE;
        }

        $this->info("Founisè vwa: {$provider->key()}");

        try {
            $voices = $provider->voices();
        } catch (ProviderException $e) {
            // Se ISIT LA yon schema ki pa matche parèt. Nou di sa klèman.
            $this->error('API a refize oswa repons lan pa lizib: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($voices === []) {
            $this->warn('API a reponn men li pa bay okenn vwa.');
            $this->line('Swa kont lan vid, swa chan yo pa rele sa nou tann nan.');

            return self::FAILURE;
        }

        $rows = [];
        $mine = 0;

        foreach ($voices as $voice) {
            $rows[] = [$voice->id, $voice->name, $voice->category];
            $mine += $voice->isMine() ? 1 : 0;
        }

        $this->table(['voice_id', 'non', 'kalite'], $rows);
        $this->info(count($voices).' vwa · '.$mine.' se pa ou.');

        return self::SUCCESS;
    }
}
