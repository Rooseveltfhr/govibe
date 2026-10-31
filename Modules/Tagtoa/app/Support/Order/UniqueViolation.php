<?php

namespace Modules\Tagtoa\App\Support\Order;

use Illuminate\Database\QueryException;

/**
 * Cette erreur SQL vient-elle de l'unicité de `client_uuid` — et d'elle SEULE ?
 *
 * Le texte d'une QueryException contient tout l'INSERT (donc « client_uuid » dans n'importe quelle erreur
 * d'insertion de commande) : chercher ce mot dans le message prenait une clé étrangère, un deadlock ou
 * un doublon de `reference` pour un doublon d'identifiant, et déclenchait un second enregistrement sans
 * clé d'idempotence. On ne regarde donc que la partie DIAGNOSTIC (avant « (Connection: … SQL: … »), et
 * seulement une violation d'unicité (MySQL 1062, SQLite « UNIQUE constraint failed »).
 */
class UniqueViolation
{
    public static function onClientUuid(QueryException $e): bool
    {
        $diagnostic = preg_split('/\s\(Connection:/', $e->getMessage())[0] ?? '';

        $isUnique = ($e->errorInfo[1] ?? null) === 1062
            || str_contains($diagnostic, 'UNIQUE constraint failed')
            || str_contains($diagnostic, 'Duplicate entry');
        if (! $isUnique) {
            return false;
        }

        // SQLite : « UNIQUE constraint failed: table.menu_id, table.client_uuid ».
        // MySQL  : « Duplicate entry '…' for key 'tagtoa_mo_menu_uuid_unique' » (nom d'index, pas de colonne).
        return str_contains($diagnostic, 'client_uuid')
            || (bool) preg_match("/for key '[^']*(client_uuid|uuid_unique)[^']*'/", $diagnostic);
    }
}
