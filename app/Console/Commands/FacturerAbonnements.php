<?php

namespace App\Console\Commands;

use App\Models\Abonnement;
use App\Services\AbonnementService;
use Illuminate\Console\Command;

class FacturerAbonnements extends Command
{
    protected $signature = 'abonnements:facturer
                            {--essai : Montre ce qui serait fait, sans rien écrire}
                            {--grace=14 : Jours de grâce avant suspension}';

    protected $description = 'Émet les factures des abonnements échus et relève les impayés';

    public function handle(AbonnementService $service): int
    {
        $simulation = (bool) $this->option('essai');
        $dus = Abonnement::afacturer()->with('client')->get();

        $this->info(($simulation ? '[SIMULATION] ' : '')."Abonnements à facturer : {$dus->count()}");

        $emises = 0;

        foreach ($dus as $abonnement) {
            $ligne = sprintf(
                '  %s — %s — %s %s',
                $abonnement->reference,
                $abonnement->plan_nom,
                number_format($abonnement->montant_ttc, 2, ',', ' '),
                $abonnement->devise
            );

            if ($simulation) {
                $this->line($ligne.'  (non émise)');

                continue;
            }

            try {
                $facture = $service->facturer($abonnement);
            } catch (\Throwable $e) {
                // Un abonnement qui échoue ne doit pas emporter les autres :
                // la tournée de facturation continue.
                $this->error($ligne.'  ÉCHEC : '.$e->getMessage());
                report($e);

                continue;
            }

            if ($facture) {
                $this->line($ligne.'  → '.$facture->reference);
                $emises++;
            }
        }

        if (! $simulation) {
            $bilan = $service->releverLesImpayes((int) $this->option('grace'));
            $this->info("Factures émises : {$emises}");
            $this->info("Passés en retard : {$bilan['en_retard']} · Suspendus : {$bilan['suspendus']}");
        }

        return self::SUCCESS;
    }
}
