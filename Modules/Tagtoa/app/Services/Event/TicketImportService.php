<?php

namespace Modules\Tagtoa\App\Services\Event;

use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;

/**
 * TAGTOA EVENT — importe en masse des billets DÉJÀ IMPRIMÉS physiquement
 * (hors système) pour les rendre vendables au terminal staff (scan QR).
 *
 * Format CSV attendu, une ligne par billet : code[,serial]
 *   - code   : la valeur encodée dans le QR imprimé (obligatoire, unique).
 *   - serial : le second code imprimé au dos du billet (optionnel, secours
 *              si le QR est illisible/abîmé).
 * Un en-tête optionnel ("code" en première colonne) est ignoré.
 */
class TicketImportService
{
    /**
     * Découpe/valide le contenu CSV brut. LOGIQUE PURE (pas de DB) : renvoie
     * les lignes valides dédupliquées DANS le fichier, + le nombre de lignes
     * ignorées (vides ou sans code).
     *
     * @return array{rows: array<int, array{code: string, serial: ?string}>, skipped: int}
     */
    public static function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $rows = [];
        $seen = [];
        $skipped = 0;

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, ',', '"', '\\');
            $code = trim((string) ($cols[0] ?? ''));
            $serial = isset($cols[1]) ? trim((string) $cols[1]) : '';

            if ($i === 0 && strtolower($code) === 'code') {
                continue; // en-tête
            }
            if ($code === '' || isset($seen[$code])) {
                $skipped++;
                continue;
            }

            $seen[$code] = true;
            $rows[] = ['code' => $code, 'serial' => $serial !== '' ? $serial : null];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * Crée les billets (statut UNSOLD) pour l'événement. Idempotent : un code
     * déjà présent dans l'événement (import précédent, doublon) est ignoré.
     *
     * @param  array<int, array{code: string, serial: ?string}>  $rows
     * @return array{created: int, duplicates: int}
     */
    public function importForEvent(Event $event, array $rows): array
    {
        $rows = array_slice($rows, 0, 5000); // borne de sécurité par lot

        $codes = array_column($rows, 'code');
        $existing = Ticket::where('event_id', $event->id)->whereIn('code', $codes)->pluck('code')->all();
        $existing = array_flip($existing);

        $created = 0;
        $duplicates = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $insert = [];
            foreach ($chunk as $row) {
                if (isset($existing[$row['code']])) {
                    $duplicates++;
                    continue;
                }
                $existing[$row['code']] = true; // anti-doublon intra-fichier déjà géré par parseCsv, ceci couvre les lots successifs
                $insert[] = [
                    'event_id'   => $event->id,
                    'code'       => $row['code'],
                    'serial'     => $row['serial'],
                    'imported'   => true,
                    'status'     => Ticket::STATUS_UNSOLD,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($insert) {
                Ticket::insert($insert);
                $created += count($insert);
            }
        }

        return ['created' => $created, 'duplicates' => $duplicates];
    }
}
