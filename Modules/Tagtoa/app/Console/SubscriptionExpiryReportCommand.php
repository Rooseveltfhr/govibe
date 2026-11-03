<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Services\Billing\PlanService;

/**
 * php artisan tagtoa:subscriptions:expiry-report
 *
 * LECTURE SEULE (R0-a). Dit combien d'abonnements payants sont bientôt échus, en grâce ou échus, et — surtout — combien
 * retomberaient sur le forfait gratuit le jour où TAGTOA_ENFORCE_EXPIRY serait allumé. À lire AVANT de l'allumer : un
 * abonné qui a payé il y a plus d'un mois sans qu'aucun renouvellement n'existe encore y figurerait à tort.
 * Ne modifie rien. N'affiche que des identifiants de commerce (aucune donnée personnelle).
 */
class SubscriptionExpiryReportCommand extends Command
{
    protected $signature = 'tagtoa:subscriptions:expiry-report {--list=20 : Nombre maximal de lignes détaillées}';

    protected $description = 'Rapport (lecture seule) des abonnements payants échus ou bientôt échus.';

    public function handle(): int
    {
        $this->line('Expiration appliquée : '.(Subscription::expiryEnforced() ? 'OUI' : 'non (TAGTOA_ENFORCE_EXPIRY éteint)').' · grâce : '.Subscription::graceDays().' j');

        // Même règle que Subscription::planFor() : tout abonnement dont le forfait n'est PAS le forfait par défaut est examiné,
        // payant ou non, daté ou non — un rapport plus étroit que la règle cacherait des abonnés qui tomberaient.
        $defaut = (string) config('tagtoa.default_plan', 'free');
        $catalogue = (array) config('tagtoa.plans', []);
        $n = ['abonnes' => 0, 'sans_fin' => 0, 'bientot' => 0, 'grace' => 0, 'echus' => 0, 'inconnus' => 0];
        $tombent = [];
        $sansAuto = [];
        foreach (Subscription::allTenants()->cursor() as $s) {
            if ($s->plan === $defaut) {
                continue;
            }
            $n['abonnes']++;
            if (! array_key_exists($s->plan, $catalogue)) {
                $n['inconnus']++;   // déjà ramené au forfait par défaut par planFor(), drapeau ou non
            }
            if ($s->expires_at === null) {
                $n['sans_fin']++;
            } else {
                match ($s->coverage()) {
                    Subscription::COVER_EXPIRING => $n['bientot']++,
                    Subscription::COVER_GRACE => $n['grace']++,
                    Subscription::COVER_EXPIRED => $n['echus']++,
                    default => null,
                };
            }
            if ($s->grantsPlan()) {
                continue;
            }
            $motif = in_array($s->status, Subscription::GRANTING_STATUSES, true) ? 'échu' : 'statut « '.$s->status.' »';
            if ($s->isQuoteOnly()) {
                $sansAuto[] = [$s, $motif];   // sur devis : jamais rétrogradé automatiquement, à traiter à la main
            } else {
                $tombent[] = [$s, $motif];
            }
        }

        $this->line('Abonnements au-dessus du forfait par défaut : '.$n['abonnes']);
        $this->line('   sans date de fin : '.$n['sans_fin']);
        $this->line('   bientôt échus : '.$n['bientot'].' · en grâce : '.$n['grace'].' · échus : '.$n['echus']);
        $this->line('   forfait inconnu du catalogue (déjà ramené au défaut) : '.$n['inconnus']);
        $this->line('Retomberaient sur le forfait gratuit si l\'expiration était appliquée : '.count($tombent));
        $this->line('Sur devis échus ou inactifs (jamais rétrogradés automatiquement, à traiter à la main) : '.count($sansAuto));

        $max = max(0, (int) $this->option('list'));
        foreach (array_slice($tombent, 0, $max) as [$s, $motif]) {
            $this->line('   '.$s->tenant_id.' · '.$s->plan.' · fin '.($s->expires_at ? $s->expires_at->format('Y-m-d') : '—').' · '.$motif);
        }
        foreach (array_slice($sansAuto, 0, $max) as [$s, $motif]) {
            $this->line('   [sur devis] '.$s->tenant_id.' · '.$s->plan.' · fin '.($s->expires_at ? $s->expires_at->format('Y-m-d') : '—').' · '.$motif);
        }

        return self::SUCCESS;
    }
}
