<?php

namespace Modules\Tagtoa\App\Console;

use Carbon\Carbon;

/**
 * Rétention par niveaux, commune aux sauvegardes de la base et des images :
 *  - les $keep plus récentes, toujours ;
 *  - la plus récente de chaque JOUR pendant $days jours (0 = pas de niveau) ;
 *  - la plus récente de chaque SEMAINE pendant $weeks semaines (0 = pas de niveau).
 * Fonction pure : elle dit quoi garder, elle ne supprime rien.
 */
class BackupRetention
{
    /**
     * @param  string[]  $files  du plus récent au plus ancien
     * @param  callable(string):Carbon  $stampOf
     * @return array<string,true>  fichiers à conserver (clés)
     */
    public static function keepSet(array $files, callable $stampOf, int $keep, int $days, int $weeks, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $garde = [];
        foreach (array_slice($files, 0, $keep) as $f) {
            $garde[$f] = true;
        }
        $vuJour = [];
        $vuSemaine = [];
        foreach ($files as $f) {   // du plus récent au plus ancien : le premier vu d'un jour / d'une semaine est son plus récent
            $t = $stampOf($f);
            $jour = $t->format('Y-m-d');
            if ($days > 0 && $t->gte($now->copy()->subDays($days)->startOfDay()) && ! isset($vuJour[$jour])) {
                $garde[$f] = true;
            }
            $vuJour[$jour] = true;
            $semaine = $t->format('o-W');
            if ($weeks > 0 && $t->gte($now->copy()->subWeeks($weeks)->startOfWeek()) && ! isset($vuSemaine[$semaine])) {
                $garde[$f] = true;
            }
            $vuSemaine[$semaine] = true;
        }

        return $garde;
    }
}
