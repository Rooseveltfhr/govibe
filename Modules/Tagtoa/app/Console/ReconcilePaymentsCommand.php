<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;

/**
 * php artisan tagtoa:pay:reconcile [--min-age=10] [--max-age-hours=72] [--limit=40]
 *
 * Re-vérifie auprès de la passerelle les paiements restés « en attente ».
 *
 * Pourquoi. Un paiement n'est constaté que quand la passerelle appelle le
 * webhook ou que le client revient sur le site. Si le webhook échoue ET que le
 * client ne revient pas (téléphone éteint, réseau perdu), l'argent est reçu et
 * la commande reste impayée pour toujours. Cette commande est le filet.
 *
 * Elle ne décide RIEN toute seule : elle appelle CheckoutService::confirmOrFail(),
 * le même chemin que le webhook — verrouillé, atomique, idempotent. La rejouer
 * ou la lancer en double ne peut pas appliquer un paiement deux fois.
 *
 * Cadence (une transaction n'est jamais interrogée plus souvent que ceci ; la
 * date de dernière vérification est `updated_at`, aucune migration) :
 *   - de --min-age à 1 h : toutes les 10 min   (le client vient de payer)
 *   - de 1 h à 24 h      : toutes les heures
 *   - de 24 h à --max-age-hours : toutes les 6 h
 * Au-delà de --max-age-hours : abandonnée, plus interrogée (comptée seulement).
 * --limit borne les appels à la passerelle par exécution (la moins récemment
 * vérifiée d'abord, donc pas de famine).
 *
 * Code de sortie 0 même quand une passerelle est en panne : un échec isolé est
 * signalé (report) et compté, il n'arrête ni les autres paiements ni le planificateur.
 */
class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'tagtoa:pay:reconcile
                            {--min-age=10 : Âge minimal (minutes) avant la première vérification}
                            {--max-age-hours=72 : Au-delà, la transaction est considérée abandonnée}
                            {--limit=40 : Appels maximum à la passerelle par exécution}';

    protected $description = 'Re-vérifie les paiements en ligne restés « en attente » (filet du webhook)';

    public function handle(CheckoutService $checkout): int
    {
        foreach (['min-age', 'max-age-hours', 'limit'] as $opt) {
            if (! ctype_digit((string) $this->option($opt)) || (int) $this->option($opt) < 1) {
                $this->error("--$opt doit être un entier >= 1.");

                return self::FAILURE;
            }
        }
        $minAge = (int) $this->option('min-age');
        $maxH   = max(2, (int) $this->option('max-age-hours'));
        $limit  = (int) $this->option('limit');

        $now = now();
        $t = fn (callable $f) => $f($now->copy());

        // Console : aucun commerce courant, la portée est ouverte ; allTenants()
        // le dit explicitement — voir TOUS les paiements est le but ici.
        $query = PayTransaction::allTenants()
            ->where('status', PayTransaction::STATUS_PENDING)
            ->where(function ($q) use ($t, $minAge, $maxH) {
                $q->where(fn ($b) => $b
                        ->where('created_at', '<=', $t(fn ($n) => $n->subMinutes($minAge)))
                        ->where('created_at', '>', $t(fn ($n) => $n->subHour()))
                        ->where('updated_at', '<=', $t(fn ($n) => $n->subMinutes(10))))
                  ->orWhere(fn ($b) => $b
                        ->where('created_at', '<=', $t(fn ($n) => $n->subHour()))
                        ->where('created_at', '>', $t(fn ($n) => $n->subDay()))
                        ->where('updated_at', '<=', $t(fn ($n) => $n->subHour())))
                  ->orWhere(fn ($b) => $b
                        ->where('created_at', '<=', $t(fn ($n) => $n->subDay()))
                        ->where('created_at', '>', $t(fn ($n) => $n->subHours($maxH)))
                        ->where('updated_at', '<=', $t(fn ($n) => $n->subHours(6))));
            })
            ->orderBy('updated_at')
            ->limit($limit);

        $paid = $failed = $pending = $errors = 0;
        foreach ($query->get() as $txn) {
            try {
                $checkout->confirmOrFail($txn->reference);
            } catch (\Throwable $e) {
                $errors++;
                report($e);
            }

            $after = PayTransaction::allTenants()->whereKey($txn->getKey())->value('status');
            match ($after) {
                PayTransaction::STATUS_PAID   => $paid++,
                PayTransaction::STATUS_FAILED => $failed++,
                default                       => $pending++,
            };

            // Marque la vérification (cadence) sans toucher au statut : un
            // paiement réglé entre-temps n'est pas réécrit.
            PayTransaction::allTenants()->whereKey($txn->getKey())
                ->where('status', PayTransaction::STATUS_PENDING)
                ->update(['updated_at' => now()]);
        }

        $abandoned = PayTransaction::allTenants()
            ->where('status', PayTransaction::STATUS_PENDING)
            ->where('created_at', '<=', $now->copy()->subHours($maxH))
            ->count();

        $this->info("Réconciliation : payées {$paid} · échouées {$failed} · toujours en attente {$pending} · erreurs {$errors} · abandonnées (> {$maxH} h) {$abandoned}");

        return self::SUCCESS;
    }
}
