<?php

namespace Modules\Agents\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Agents\Runtime\AgentBuilder;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIServices\Transcription\TranscriptionService;

/**
 * Teste yon ajan dirèk nan tèminal la, ak vrè router la — sa ki dèyè
 * bouton « Demo » a, san entèfas web.
 *
 * Sèvi pou verifye lokalman anvan deplwaman: si demo a mache isit la ak yon
 * vrè kle API, li ap mache menm jan sou WhatsApp/telefòn lè kanal sa yo bati.
 */
class DemoAgentCommand extends Command
{
    protected $signature = 'agents:demo
        {sector : restaurant|clinic|school}
        {name : Non biznis la, pou demo a — ex: "Ti Kafe"}
        {--key=demo : Kle inik ajan an}
        {--lang= : Lang demo a (ht|fr|en|es) — defo: lang sektè a}
        {--knowledge=* : Konesans kle=valè, ex: --knowledge="orè=10h-22h"}
        {--question=* : Kesyon pèsonalize — sinon kesyon egzanp sektè a}
        {--audio= : Chemen yon fichye odyo (.mp3/.wav/.m4a) — transkri l epi sèvi kòm kesyon}';

    protected $description = 'Fè yon ajan reponn kesyon demo (tèks oswa vwa) ak vrè router la, dirèk nan tèminal la.';

    public function handle(AgentBuilder $builder, ProviderRegistry $providers, TranscriptionService $transcription): int
    {
        $spoken = false;
        $questions = $this->option('question') ?: null;

        $audioPath = $this->option('audio');
        if ($audioPath !== null) {
            if (! is_file($audioPath)) {
                $this->error("Fichye odyo a pa egziste: {$audioPath}");

                return self::FAILURE;
            }

            try {
                $result = $transcription->transcribe($audioPath, $this->option('lang') ?: null);
            } catch (NoProviderAvailableException $e) {
                $this->error("Pa gen okenn founisè transkripsyon (vwa→tèks) konfigire: {$e->getMessage()}");
                $this->line('Ajoute yon kle API ki sipòte transkripsyon (ex: OPENAI_API_KEY pou Whisper) nan .env.');

                return self::FAILURE;
            }

            $this->info("🎙️  Vwa transkri ({$result['provider']}): \"{$result['text']}\"");
            $spoken = true;
            $questions = [$result['text']];
        }

        if ($providers->configured() === []) {
            $this->error('Pa gen okenn founisè AI konfigire (OPENAI_API_KEY, ANTHROPIC_API_KEY…).');
            $this->line('Ajoute omwen yon kle API nan .env pou w ka gen yon vrè repons, epi eseye ankò.');

            return self::FAILURE;
        }

        $knowledge = [];
        foreach ($this->option('knowledge') as $pair) {
            [$label, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            if ($label !== '') {
                $knowledge[$label] = $value;
            }
        }

        try {
            $agent = $builder->create(
                sector: $this->argument('sector'),
                key: $this->option('key'),
                name: $this->argument('name'),
                knowledge: $knowledge,
            );
        } catch (InvalidArgumentException $e) {
            $sectors = implode(', ', array_map(fn ($t) => $t->sector, $builder->availableSectors()));
            $this->error($e->getMessage());
            $this->line("Sektè disponib: {$sectors}");

            return self::FAILURE;
        }

        try {
            $turns = $builder->demo($agent, $questions, $this->option('lang') ?: null);
        } catch (NoProviderAvailableException $e) {
            $this->error("Router la pa jwenn okenn founisè disponib pou fè demo a: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['Kesyon', 'Repons', 'Founisè', 'Modèl', 'Latans (ms)'],
            array_map(fn ($turn) => [
                $turn->question,
                $turn->answer,
                $turn->provider,
                $turn->model,
                $turn->latencyMs,
            ], $turns),
        );

        if ($spoken) {
            $this->comment('ℹ️  Sa a se soti nan vwa: nenpòt aksyon ekri (kòmand, randevou) ta mande konfimasyon anvan li egzekite.');
        }

        return self::SUCCESS;
    }
}
