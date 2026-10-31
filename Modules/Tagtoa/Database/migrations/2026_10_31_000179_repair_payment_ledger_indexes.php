<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Répare la table du grand livre de paiement (migration 000171) sur les bases
 * où elle a été créée SANS ses deux index.
 *
 * Sur MySQL, le nom d'index généré (69 caractères) dépasse la limite de 64 : la
 * migration créait la table, échouait sur l'index, puis — le garde `hasTable`
 * faisant le reste — ne les posait plus jamais. La table fonctionne sans, mais
 * chaque lecture par commerce ou par paiement la parcourt en entier.
 *
 * Ne peut JAMAIS faire échouer `migrate` : un échec ici désactiverait le module
 * entier au déploiement. Tout est gardé et avalé ; au pire les index manquent
 * encore (ce qui est l'état d'avant).
 */
return new class extends Migration
{
    private const TABLE = 'tagtoa_payment_ledger_entries';

    private const INDEXES = [
        'tagtoa_ple_tenant_payable_idx' => '(`tenant_id`, `payable_type`, `payable_id`)',
        'tagtoa_ple_payable_status_idx' => '(`payable_type`, `payable_id`, `to_status`)',
    ];

    public function up(): void
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable(self::TABLE)) {
                return;
            }
            foreach (self::INDEXES as $name => $columns) {
                $exists = DB::select('SHOW INDEX FROM `'.self::TABLE.'` WHERE Key_name = ?', [$name]);
                if (empty($exists)) {
                    DB::statement('ALTER TABLE `'.self::TABLE.'` ADD INDEX `'.$name.'` '.$columns);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        // Volontairement vide : ces index sont ceux que la création de la table
        // aurait dû poser ; les retirer ne répare rien.
    }
};
