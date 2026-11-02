<?php

namespace Modules\Tagtoa\App\Support\Hotel;

/**
 * TAGTOA HÔTEL (H5) — règles des demandes d'invité. Classe pure ; le serveur revalide tout.
 */
class HotelRequestRules
{
    public const STATUSES = [
        'new'          => 'Nouvelle',
        'acknowledged' => 'Prise en compte',
        'in_progress'  => 'En cours',
        'done'         => 'Terminée',
        'rejected'     => 'Refusée',
        'cancelled'    => 'Annulée',
    ];

    /** Une demande « ouverte » compte dans le plafond par chambre et peut encore évoluer. */
    public const OPEN = ['new', 'acknowledged', 'in_progress'];

    /** Transitions permises côté hôtel (l'annulation est le fait de l'invité, tant que rien n'est pris en compte). */
    public const TRANSITIONS = [
        'new'          => ['acknowledged', 'in_progress', 'done', 'rejected'],
        'acknowledged' => ['in_progress', 'done', 'rejected'],
        'in_progress'  => ['done', 'rejected'],
    ];

    /**
     * Natures de service qui se DEMANDENT depuis le portail. Le room service passe par une commande
     * (suite de H5) ; « informations » n'appelle personne.
     */
    public const REQUESTABLE_KINDS = ['housekeeping', 'linen', 'reception', 'maintenance', 'reservation'];

    /** Demandes ouvertes au plus par chambre : un QR photographié ne doit pas inonder la réception. */
    public const OPEN_MAX_PER_ROOM = 5;

    public const NOTE_MAX = 300;
    public const REASON_MAX = 200;

    /** Un lien de suivi cesse de fonctionner après ce délai (jours). */
    public const TRACK_DAYS = 30;

    /** Durée de validité du jeton émis avec la page (secondes). */
    public const TOKEN_TTL = 10800;

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Texte libre de l'invité : sans caractères de contrôle, borné. Toujours échappé à l'affichage. */
    public static function cleanNote(?string $note): ?string
    {
        $n = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $note) ?? '');

        return $n === '' ? null : mb_substr($n, 0, self::NOTE_MAX);
    }
}
