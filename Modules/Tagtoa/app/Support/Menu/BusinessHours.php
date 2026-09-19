<?php

namespace Modules\Tagtoa\App\Support\Menu;

/**
 * TAGTOA MENU — le commerce est-il ouvert MAINTENANT ?
 *
 * `tagtoa_sites.hours` existait déjà, mais en JSON libre `[{day,value}]` :
 * bon pour de l'affichage, impossible à évaluer côté serveur. Ici chaque
 * jour porte une heure d'ouverture et de fermeture structurées
 * (`{"mon": {"open":"08:00","close":"20:00"}}`), un jour absent ou `null`
 * voulant dire fermé ce jour-là — c'est ce qui permet de REFUSER une
 * commande hors horaires, pas seulement de l'afficher.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class BusinessHours
{
    /** Ordre ISO-8601 (1 = lundi … 7 = dimanche), utilisé par DateTime::format('N'). */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Nettoie ce qu'a envoyé le formulaire. Un jour coché « fermé », une
     * heure absente ou mal formée devient `null` — jamais une exception, et
     * jamais une plage à moitié renseignée qui ne voudrait rien dire.
     *
     * Renvoie null quand aucun jour n'a d'horaire : on évite d'écrire un
     * objet JSON qui ne dit rien.
     */
    public static function sanitize(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $out = [];
        foreach (self::DAYS as $day) {
            $row = $input[$day] ?? null;

            if (! is_array($row) || ! empty($row['closed'])) {
                $out[$day] = null;
                continue;
            }

            $open = self::validTime($row['open'] ?? null);
            $close = self::validTime($row['close'] ?? null);
            $out[$day] = ($open !== null && $close !== null) ? ['open' => $open, 'close' => $close] : null;
        }

        return array_filter($out) ? $out : null;
    }

    /** « 08:00 »-« 23:59 », sinon null. PUR. */
    private static function validTime(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Le commerce est-il ouvert à CET instant ? PUR.
     *
     * `$hours` non configuré (null) = toujours ouvert : un menu qui n'a
     * jamais renseigné d'horaires ne doit pas se retrouver fermé par
     * défaut, ce qui serait un mensonge plus grave que de ne rien afficher.
     *
     * Une plage dont la fermeture est avant l'ouverture (18:00-02:00) est
     * traitée comme traversant minuit — le cas d'un bar ou d'un club, cité
     * explicitement par le fondateur.
     */
    public static function isOpenAt(?array $hours, \DateTimeInterface $at): bool
    {
        if (! $hours) {
            return true;
        }

        $jour = self::DAYS[((int) $at->format('N')) - 1];
        $plage = $hours[$jour] ?? null;
        if (! $plage) {
            return false; // explicitement fermé ce jour
        }

        $minuteCourante = ((int) $at->format('H')) * 60 + (int) $at->format('i');
        $ouverture = self::toMinutes($plage['open'] ?? null);
        $fermeture = self::toMinutes($plage['close'] ?? null);
        if ($ouverture === null || $fermeture === null) {
            return false; // plage corrompue : ne jamais affirmer ouvert sans preuve
        }

        if ($fermeture <= $ouverture) {
            return $minuteCourante >= $ouverture || $minuteCourante < $fermeture;
        }

        return $minuteCourante >= $ouverture && $minuteCourante < $fermeture;
    }

    private static function toMinutes(mixed $heure): ?int
    {
        if (! is_string($heure) || ! preg_match('/^(\d{2}):(\d{2})$/', $heure, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /** « 08:00 – 20:00 », ou « Fermé » — ce qui s'affiche pour un jour donné. PUR. */
    public static function rangeLabel(?array $hours, string $day): string
    {
        $plage = $hours[$day] ?? null;

        return $plage ? ($plage['open'].' – '.$plage['close']) : 'Fermé';
    }
}
