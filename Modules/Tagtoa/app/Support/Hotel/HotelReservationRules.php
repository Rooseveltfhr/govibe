<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Carbon\Carbon;

/**
 * TAGTOA HÔTEL (HM-3) — règles des demandes de réservation. Classe pure ; le serveur revalide tout.
 */
class HotelReservationRules
{
    public const STATUSES = [
        'new'       => 'Nouvelle',
        'confirmed' => 'Confirmée',
        'refused'   => 'Refusée',
        'cancelled' => 'Annulée',
    ];

    /** « Ouverte » : attend encore l'hôtel (nouvelle) ou le séjour (confirmée). */
    public const OPEN = ['new', 'confirmed'];

    /** Côté hôtel. Une confirmée peut être retirée (refusée) ; l'annulation est le fait de l'invité. */
    public const TRANSITIONS = [
        'new'       => ['confirmed', 'refused'],
        'confirmed' => ['refused'],
    ];

    public const NIGHTS_MAX = 30;
    public const GUESTS_MAX = 20;
    public const HORIZON_DAYS = 365;
    public const NOTE_MAX = 300;
    public const REASON_MAX = 200;
    public const NAME_MAX = 120;

    /** Au plus N demandes NOUVELLES par téléphone et par hôtel (une demande oubliée cesse de compter après N jours). */
    public const NEW_MAX_PER_PHONE = 3;
    public const NEW_FRESH_DAYS = 7;

    /** Plafond par heure, par hôtel : borne le spam sans gêner un vrai flux de demandes. */
    public const PER_HOTEL_HOURLY = 60;

    /** Rétention: données de l'invité effacées après ce délai (jours). */
    public const PURGE_DAYS = 90;

    /** Un lien de suivi cesse de fonctionner après ce délai (jours). */
    public const TRACK_DAYS = 60;

    /** @return array<string,mixed> */
    public static function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'min:1'],
            'date'    => ['required', 'date_format:Y-m-d'],
            'nights'  => ['nullable', 'integer', 'min:1', 'max:'.self::NIGHTS_MAX],
            'time'    => ['nullable', 'date_format:H:i'],
            'guests'  => ['nullable', 'integer', 'min:1', 'max:'.self::GUESTS_MAX],
            'name'    => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'phone'   => ['required', 'string', 'max:40'],
            'note'    => ['nullable', 'string', 'max:'.self::NOTE_MAX],
        ];
    }

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Chiffres seuls, 8 à 15 : sinon null (numéro inutilisable). */
    public static function cleanPhone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($d) >= 8 && strlen($d) <= 15 ? $d : null;
    }

    /** Clé de rapprochement: les 8 derniers chiffres (« 3712-4455 » et « +509 3712 4455 » sont le même numéro). */
    public static function phoneKey(string $digits): string
    {
        return substr($digits, -8);
    }

    /** Texte libre: sans caractères de contrôle ni balises, borné. Toujours échappé à l'affichage. */
    public static function cleanText(?string $v, int $max): ?string
    {
        $t = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags((string) $v)) ?? '');
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');

        return $t === '' ? null : mb_substr($t, 0, $max);
    }

    /** Une date ne se réserve ni dans le passé, ni au-delà de l'horizon. */
    public static function dateOk(Carbon $date, Carbon $today): bool
    {
        return $date->greaterThanOrEqualTo($today->copy()->startOfDay())
            && $date->lessThanOrEqualTo($today->copy()->addDays(self::HORIZON_DAYS)->endOfDay());
    }

    /** Total: nuits × prix pour une nuit, le prix du moment sinon. Arrondi au centime. */
    public static function total(string $kind, float $price, int $nights): float
    {
        return round($kind === 'night' ? $price * max(1, $nights) : $price, 2);
    }
}
