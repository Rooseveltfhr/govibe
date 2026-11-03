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
        $plans = PlanService::effectivePlans();
        $this->line('Expiration appliquée : '.(Subscription::expiryEnforced() ? 'OUI' : 'non (TAGTOA_ENFORCE_EXPIRY éteint)').' · grâce : '.Subscription::graceDays().' j');

        $payants = Subscription::allTenants()->whereNotNull('expires_at')->orderBy('expires_at')->get()
            ->filter(fn (Subscription $s) => (float) ($plans[$s->plan]['price'] ?? 0) > 0);

        $compte = [Subscription::COVER_ACTIVE => 0, Subscription::COVER_EXPIRING => 0, Subscription::COVER_GRACE => 0, Subscription::COVER_EXPIRED => 0];
        $aTomber = [];
        foreach ($payants as $s) {
            $c = $s->coverage();
            $compte[$c]++;
            if (! $s->grantsPlan()) {
                $aTomber[] = $s;
            }
        }

        $this->line('Abonnements payants avec date de fin : '.$payants->count()
            .' · en cours '.$compte[Subscription::COVER_ACTIVE]
            .' · bientôt échus '.$compte[Subscription::COVER_EXPIRING]
            .' · en grâce '.$compte[Subscription::COVER_GRACE]
            .' · échus '.$compte[Subscription::COVER_EXPIRED]);
        $this->line('Retomberaient sur le forfait gratuit si l\'expiration était appliquée : '.count($aTomber));

        $max = max(0, (int) $this->option('list'));
        foreach (array_slice($aTomber, 0, $max) as $s) {
            $this->line('   '.$s->tenant_id.' · '.$s->plan.' · fin le '.$s->expires_at->format('Y-m-d').' · statut '.$s->status);
        }

        return self::SUCCESS;
    }
}
