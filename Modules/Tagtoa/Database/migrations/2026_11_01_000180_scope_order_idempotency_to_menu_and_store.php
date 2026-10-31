<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'idempotence d'une commande vaut POUR UN MENU (ou UNE BOUTIQUE), pas pour toute la plateforme.
 *
 * `client_uuid` était unique GLOBALEMENT. Conséquences : un identifiant rejoué contre un autre commerce
 * heurtait la commande d'autrui (et le service la lui rendait — référence, total, lien de suivi) ; deux
 * commerces ne pouvaient pas partager un identifiant. L'unicité devient (menu_id, client_uuid) /
 * (store_id, client_uuid).
 *
 * ORDRE (leçon de l'incident #206 : MySQL refuse de retirer un index qui soutient une clé étrangère) :
 *   1. AJOUTER le nouvel index composite — il commence par la clé étrangère, qu'il soutient aussi ;
 *   2. dans un appel SÉPARÉ, RETIRER l'ancien index unique (sur client_uuid seul : il ne soutient aucune clé).
 * Aucune donnée ne peut violer le nouvel index : l'ancien, plus strict, l'a déjà garanti. Chaque étape est
 * gardée (rejouable après un échec partiel : `migrate` ne doit jamais retomber sur « index already exists »,
 * car un échec désactiverait le module entier au déploiement).
 */
return new class extends Migration
{
    private const TABLES = [
        'tagtoa_menu_orders'  => ['fk' => 'menu_id',  'old' => 'tagtoa_menu_orders_client_uuid_unique',  'new' => 'tagtoa_mo_menu_uuid_unique'],
        'tagtoa_store_orders' => ['fk' => 'store_id', 'old' => 'tagtoa_store_orders_client_uuid_unique', 'new' => 'tagtoa_so_store_uuid_unique'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $c) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $c['fk']) || ! Schema::hasColumn($table, 'client_uuid')) {
                continue;
            }

            if (! $this->indexExists($table, $c['new'])) {
                Schema::table($table, function (Blueprint $t) use ($c) {
                    $t->unique([$c['fk'], 'client_uuid'], $c['new']);
                });
            }

            // Appel séparé : jamais ensemble avec l'ajout ci-dessus.
            if ($this->indexExists($table, $c['old'])) {
                Schema::table($table, function (Blueprint $t) use ($c) {
                    $t->dropUnique($c['old']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $c) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                if (! $this->indexExists($table, $c['old'])) {
                    Schema::table($table, function (Blueprint $t) {
                        $t->unique('client_uuid');
                    });
                }
            } catch (\Throwable $e) {
                // Des identifiants identiques existent déjà dans deux commerces : l'ancien index global
                // ne peut plus être recréé. On garde le composite (plus permissif, sans danger).
                report($e);
                continue;
            }

            if ($this->indexExists($table, $c['new'])) {
                // Le composite soutient la clé étrangère : MySQL refuse de le retirer (erreur 1553) tant
                // qu'aucun autre index ne commence par cette colonne. On pose d'abord un index simple.
                $plain = $table.'_'.$c['fk'].'_index';
                if (! $this->indexExists($table, $plain)) {
                    Schema::table($table, function (Blueprint $t) use ($c, $plain) {
                        $t->index($c['fk'], $plain);
                    });
                }
                // Appel séparé, après l'ajout ci-dessus.
                Schema::table($table, function (Blueprint $t) use ($c) {
                    $t->dropUnique($c['new']);
                });
            }
        }
    }

    /** Un index existe-t-il (MySQL/MariaDB, SQLite) ? Sans dépendre de doctrine/dbal. */
    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();
        $name = DB::connection()->getTablePrefix().$table;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return ! empty(DB::select('SHOW INDEX FROM `'.$name.'` WHERE Key_name = ?', [$index]));
        }
        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA index_list("'.$name.'")') as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        return false;
    }
};
