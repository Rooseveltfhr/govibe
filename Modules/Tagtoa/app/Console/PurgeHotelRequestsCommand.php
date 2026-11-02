<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Tagtoa\App\Models\Hotel\HotelRequest;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules as R;

/**
 * php artisan tagtoa:hotel:purge-requests [--days=90]
 *
 * Rétention des demandes d'invités (H5) : le texte libre (précision de l'invité, motif de refus) est
 * EFFACÉ pour les demandes closes depuis plus de N jours, et pour toute demande vieille de plus de
 * 2×N jours même restée ouverte. La ligne (service, statut, dates) reste pour les statistiques ;
 * seul ce qu'un invité a pu écrire disparaît. Rejouable sans effet.
 */
class PurgeHotelRequestsCommand extends Command
{
    protected $signature = 'tagtoa:hotel:purge-requests {--days= : Délai en jours (défaut 90)}';

    protected $description = 'Efface le texte libre des vieilles demandes d\'invités (hôtel).';

    public static function schedule(Schedule $schedule): void
    {
        $schedule->command('tagtoa:hotel:purge-requests')->dailyAt('03:17')->withoutOverlapping(60);
    }

    public function handle(): int
    {
        $jours = max(1, (int) ($this->option('days') ?: R::PURGE_DAYS));
        $seuil = now()->subDays($jours);
        $seuilOuvertes = now()->subDays($jours * 2);

        $n = HotelRequest::allTenants()
            ->where(fn ($q) => $q->whereNotNull('note')->orWhereNotNull('reason'))
            ->where(function ($q) use ($seuil, $seuilOuvertes) {
                $q->where(fn ($c) => $c->whereNotIn('status', R::OPEN)->where('updated_at', '<', $seuil))
                  ->orWhere('created_at', '<', $seuilOuvertes);
            })
            ->update(['note' => null, 'reason' => null]);

        $this->info("Demandes d'invités : texte libre effacé sur $n ligne(s).");

        return self::SUCCESS;
    }
}
