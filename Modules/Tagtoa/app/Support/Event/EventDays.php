<?php

namespace Modules\Tagtoa\App\Support\Event;

/**
 * TAGTOA EVENT — jours d'un événement (multi-jour), logique PURE.
 *
 * Un festival peut durer plusieurs jours (ex. « Pass 2 jours »). Le check-in
 * doit alors autoriser UNE entrée PAR JOUR (et bloquer une 2ᵉ entrée le même
 * jour). Cette classe calcule la liste des jours entre le début et la fin, et
 * décide du jour courant / si une entrée a déjà eu lieu un jour donné.
 *
 * Rétro-compatible : un événement d'un seul jour ⇒ un seul jour ⇒ comportement
 * identique à « une entrée unique ».
 */
class EventDays
{
    /** Borne de sécurité : un événement ne peut pas s'étaler indéfiniment. */
    public const MAX_DAYS = 60;

    /**
     * Liste des jours (YYYY-MM-DD) de $start à $end inclus.
     * Entrées nulles/incohérentes ⇒ tableau vide (l'appelant retombe sur « today »).
     *
     * @param  string|null  $start  date/datetime ISO
     * @param  string|null  $end    date/datetime ISO
     * @return string[]
     */
    public static function list(?string $start, ?string $end): array
    {
        if (! $start) {
            return [];
        }
        $s = self::toDate($start);
        $e = $end ? self::toDate($end) : $s;
        if ($s === null || $e === null || $e < $s) {
            return [];
        }

        $days = [];
        $cur = $s;
        while ($cur <= $e && count($days) < self::MAX_DAYS) {
            $days[] = $cur;
            $cur = date('Y-m-d', strtotime($cur.' +1 day'));
        }

        return $days;
    }

    /**
     * Choisit le jour de check-in effectif : le jour demandé s'il est valide,
     * sinon « today » s'il tombe dans l'événement, sinon le 1ᵉʳ jour, sinon today.
     *
     * @param  string[]  $days      jours de l'événement (issus de list())
     * @param  string    $today     date du jour (YYYY-MM-DD)
     * @param  string|null  $requested  jour demandé par le staff (optionnel)
     */
    public static function resolveDay(array $days, string $today, ?string $requested = null): string
    {
        if ($requested !== null && in_array($requested, $days, true)) {
            return $requested;
        }
        if ($days === [] || in_array($today, $days, true)) {
            return $today;
        }

        return $days[0];
    }

    /**
     * Une entrée a-t-elle déjà eu lieu le jour $day ?
     *
     * @param  string[]  $inDates  dates (YYYY-MM-DD) des entrées « in » du billet
     */
    public static function alreadyEntered(array $inDates, string $day): bool
    {
        return in_array($day, $inDates, true);
    }

    /** Normalise une date/datetime ISO en YYYY-MM-DD, ou null si invalide. */
    private static function toDate(string $value): ?string
    {
        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
