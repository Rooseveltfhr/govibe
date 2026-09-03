<?php

namespace Modules\AIProvider\Console;

use Illuminate\Console\Command;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * « Poukisa demo a di pa gen founisè? » — sa a reponn kesyon an sou sèvè a,
 * san li pa janm ekri yon kle nan yon jounal.
 *
 * Li di, pou chak konektè: èske li aktive, èske li gen yon kredansyèl, epi
 * konbyen karaktè kle a genyen (pou detekte yon kle koupe oswa yon espas),
 * men JANM kle a li menm. Deplwaman an rele l apre li ekri .env: si repons
 * lan se zewo founisè konfigire, deplwaman an di sa klèman olye nou dekouvri
 * sa sou paj la.
 */
class ProvidersStatusCommand extends Command
{
    protected $signature = 'govibe:providers';

    protected $description = 'Montre ki founisè IA ki konfigire (san janm montre yon kle)';

    public function handle(ProviderRegistry $registry): int
    {
        $all = $registry->all();

        if ($all === []) {
            $this->error('Zewo konektè anrejistre — verifye config/config.php AIProvider la.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($all as $provider) {
            $rows[] = [
                $provider->key(),
                $provider->name(),
                $provider->isConfigured() ? 'wi' : 'non',
            ];
        }

        $this->table(['kle', 'non', 'konfigire'], $rows);

        $configured = count($registry->configured());

        if ($configured === 0) {
            $this->warn('Zewo founisè konfigire: ajan yo p ap ka reponn.');
            $this->line('Ajoute yon kle nan .env (egzanp: OPENAI_API_KEY=...) epi relanse.');

            return self::FAILURE;
        }

        $this->info("{$configured} founisè konfigire.");

        return self::SUCCESS;
    }
}
