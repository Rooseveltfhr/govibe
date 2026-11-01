<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEPLOY — toutes les migrations sur un VRAI MySQL/MariaDB
|--------------------------------------------------------------------------
| La CI « DB réelle » tourne sur SQLite, qui accepte tout. Résultat : la
| migration 000171 échouait sur MySQL (nom d'index de 69 caractères, limite 64)
| depuis son ajout, sans que rien ne le dise — une base neuve (reprise après
| sinistre, nouvel environnement) ne pouvait pas migrer, et la production avait
| gardé une table sans ses index.
|
| Ce test migre une base VIDE de bout en bout, défait et refait la dernière
| migration, puis rejoue la réparation du grand livre sur une table privée de
| ses index. Ignoré sans TAGTOA_TEST_MYSQL_* (HOST, PORT, USER, PASS) et
| TAGTOA_TEST_MYSQL_DB_MIGRATE : une base VIDE — TOUTES ses tables sont supprimées.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\Tests\TestCase;

class MysqlMigrationsRealTest extends TestCase
{
    use RefreshDatabase;

    private string $original = 'testbench';

    private function env(string $k, $d = null)
    {
        return getenv('TAGTOA_TEST_MYSQL_'.$k) ?: $d;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->env('HOST') || ! $this->env('USER') || ! $this->env('DB_MIGRATE')) {
            $this->markTestSkipped('TAGTOA_TEST_MYSQL_* / DB_MIGRATE absent : migrations sur MySQL réel ignorées.');
        }
        $this->original = config('database.default');
        config(['database.connections.realmig' => [
            'driver' => 'mysql', 'host' => $this->env('HOST'), 'port' => $this->env('PORT', '3306'),
            'database' => $this->env('DB_MIGRATE'), 'username' => $this->env('USER'), 'password' => (string) $this->env('PASS', ''),
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
        ], 'database.default' => 'realmig']);
        DB::purge('realmig');
        Schema::dropAllTables();
    }

    protected function tearDown(): void
    {
        // Testbench défait ses migrations sur la connexion par défaut : la remettre avant.
        config(['database.default' => $this->original]);
        parent::tearDown();
    }

    private function params(): array
    {
        return ['--path' => realpath(__DIR__.'/../../Database/migrations'), '--realpath' => true, '--force' => true];
    }

    /** Nombre de migrations dont le nom est >= $prefixe (jusqu'à la dernière). */
    private function stepsFrom(string $prefixe): int
    {
        $noms = collect(glob(__DIR__.'/../../Database/migrations/*.php'))->map(fn ($f) => basename($f, '.php'))->sort()->values();
        $debut = $noms->search(fn ($n) => str_starts_with($n, $prefixe));
        $this->assertNotFalse($debut, "Migration $prefixe introuvable.");

        return $noms->count() - $debut;
    }

    private function indexNames(string $table): array
    {
        return collect(DB::select("SHOW INDEX FROM `$table`"))->pluck('Key_name')->unique()->values()->all();
    }

    public function test_every_migration_runs_on_an_empty_database_and_everything_since_000178_rolls_back_and_replays(): void
    {
        $code = Artisan::call('migrate', $this->params());
        $this->assertSame(0, $code, Artisan::output());

        // CS-7 : l'unicité des commandes est par menu / par boutique, plus globale.
        $this->assertContains('tagtoa_mo_menu_uuid_unique', $this->indexNames('tagtoa_menu_orders'));
        $this->assertNotContains('tagtoa_menu_orders_client_uuid_unique', $this->indexNames('tagtoa_menu_orders'));
        $this->assertContains('tagtoa_so_store_uuid_unique', $this->indexNames('tagtoa_store_orders'));
        $this->assertNotContains('tagtoa_store_orders_client_uuid_unique', $this->indexNames('tagtoa_store_orders'));

        // Défaire TOUT ce qui est postérieur à 000178 inclus (000178 → dernière), puis refaire.
        // Le nombre d'étapes est CALCULÉ : « les trois dernières » cessait de couvrir 000180 dès
        // qu'une migration plus récente s'ajoutait (H1, H2…), et ce test échouait pour une
        // raison étrangère à la migration qu'il protège.
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $this->stepsFrom('2026_10_31_000178')] + $this->params()), Artisan::output());
        $this->assertContains('tagtoa_menu_orders_client_uuid_unique', $this->indexNames('tagtoa_menu_orders'));
        $this->assertNotContains('tagtoa_mo_menu_uuid_unique', $this->indexNames('tagtoa_menu_orders'));
        $this->assertSame(0, Artisan::call('migrate', $this->params()), Artisan::output());
        $this->assertContains('tagtoa_mo_menu_uuid_unique', $this->indexNames('tagtoa_menu_orders'));
    }

    public function test_the_ledger_repair_adds_the_missing_indexes_and_is_idempotent(): void
    {
        $this->assertSame(0, Artisan::call('migrate', $this->params()), Artisan::output());
        foreach (['tagtoa_ple_tenant_payable_idx', 'tagtoa_ple_payable_status_idx'] as $i) {
            DB::statement("ALTER TABLE tagtoa_payment_ledger_entries DROP INDEX $i"); // l'état de la production
        }
        $this->assertCount(0, DB::select("SHOW INDEX FROM tagtoa_payment_ledger_entries WHERE Key_name LIKE 'tagtoa_ple_%'"));

        $repair = require __DIR__.'/../../Database/migrations/2026_10_31_000179_repair_payment_ledger_indexes.php';
        $repair->up();
        $repair->up(); // rejouable

        $names = collect(DB::select("SHOW INDEX FROM tagtoa_payment_ledger_entries WHERE Key_name LIKE 'tagtoa_ple_%'"))->pluck('Key_name')->unique()->sort()->values()->all();
        $this->assertSame(['tagtoa_ple_payable_status_idx', 'tagtoa_ple_tenant_payable_idx'], $names);
    }
}
