<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU (UX-2) — un numéro COURT de commande, dicible en cuisine (« numéro 42 »).
 *
 * `CMD-WKJJGOPL` (8 lettres au hasard) reste la référence : suivi client, liens, audit — rien ne change. Le numéro court s'y ajoute :
 *  - `order_day` : le JOUR DE SERVICE, à l'heure locale du menu (colonne `timezone`, sinon Port-au-Prince) : il commence à 4 h, un service de
 *                  nuit reste donc dans la journée de la veille (Order::serviceDay) ;
 *  - `order_no`  : 1, 2, 3… pour CE menu et CE jour (la numérotation repart à 1 chaque matin, comme un ticket de cuisine).
 * L'unicité (menu, jour, numéro) est garantie par la base ; le calcul se fait sous verrou (MenuOrderService).
 *
 * Aditive : colonnes NULLABLES, aucune réécriture des commandes existantes (elles gardent leur référence seule).
 * Rejouable et réversible.
 */
return new class extends Migration
{
    private const INDEX = 'tagtoa_mo_day_no_uq';

    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_menu_orders')) {
            return;
        }
        Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_menu_orders', 'order_day')) {
                $t->date('order_day')->nullable();
            }
            if (! Schema::hasColumn('tagtoa_menu_orders', 'order_no')) {
                $t->unsignedInteger('order_no')->nullable();
            }
        });
        if (! $this->indexExists()) {
            Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
                // NULL n'est jamais égal à NULL : les anciennes commandes (sans numéro) ne se heurtent pas entre elles.
                $t->unique(['menu_id', 'order_day', 'order_no'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tagtoa_menu_orders')) {
            return;
        }
        if ($this->indexExists()) {
            Schema::table('tagtoa_menu_orders', fn (Blueprint $t) => $t->dropUnique(self::INDEX));
        }
        Schema::table('tagtoa_menu_orders', function (Blueprint $t) {
            foreach (['order_no', 'order_day'] as $c) {
                if (Schema::hasColumn('tagtoa_menu_orders', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }

    /**
     * L'index existe-t-il ? Sans Schema::getIndexes() (absent des Laravel d'avant 10.3x : l'hôte de production n'est pas celui des
     * tests) : on interroge la base elle-même, comme les migrations 000179/000180.
     */
    private function indexExists(): bool
    {
        try {
            $driver = DB::connection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                return ! empty(DB::select('SHOW INDEX FROM `tagtoa_menu_orders` WHERE Key_name = ?', [self::INDEX]));
            }
            if ($driver === 'sqlite') {
                foreach (DB::select("PRAGMA index_list('tagtoa_menu_orders')") as $i) {
                    if (($i->name ?? null) === self::INDEX) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // doute : on laisse up() tenter la création (une erreur claire vaut mieux qu'un index silencieusement absent)
        }

        return false;
    }
};
