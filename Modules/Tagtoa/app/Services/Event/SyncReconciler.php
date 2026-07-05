<?php

namespace Modules\Tagtoa\App\Services\Event;

/**
 * TAGTOA EVENT — logique pure de réconciliation offline → serveur.
 *
 * Idempotence via `client_uuid` (même convention que Menu/Booking/Review/POS).
 * Rien ici ne dépend de Laravel : c'est la logique décidable en amont de
 * l'écriture en base.
 */
class SyncReconciler
{
    /**
     * Partitionne un lot d'enregistrements offline selon leur `client_uuid`.
     *
     * - un uuid déjà connu du serveur ($seenUuids) => doublon (à ignorer) ;
     * - un uuid répété DANS le lot lui-même => seul le premier est "fresh" ;
     * - un enregistrement sans uuid passe toujours (traité comme unique).
     *
     * @param  array  $records     liste d'items ayant (au besoin) une clé 'client_uuid'
     * @param  array  $seenUuids   uuids déjà présents côté serveur
     * @return array{fresh: array, duplicates: array}  PUR
     */
    public static function partition(array $records, array $seenUuids): array
    {
        $seen = array_fill_keys(array_values($seenUuids), true);
        $fresh = [];
        $duplicates = [];

        foreach ($records as $r) {
            $uuid = is_array($r) ? ($r['client_uuid'] ?? null) : null;

            if ($uuid !== null && isset($seen[$uuid])) {
                $duplicates[] = $r;

                continue;
            }

            if ($uuid !== null) {
                $seen[$uuid] = true; // déduplique aussi à l'intérieur du lot
            }
            $fresh[] = $r;
        }

        return ['fresh' => $fresh, 'duplicates' => $duplicates];
    }

    /**
     * Double check-in : un billet déjà entré qu'on re-scanne en entrée = conflit
     * (le premier gagne). Une sortie ('out') n'est pas un conflit d'entrée. PUR.
     */
    public static function isDuplicateEntry(bool $alreadyEntered, string $direction = 'in'): bool
    {
        return $alreadyEntered && $direction === 'in';
    }
}
