<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — incident production : une migration qui refuse un redéploiement
|--------------------------------------------------------------------------
| Constaté dans les logs de déploiement (GitHub Actions, run 40652897604 et
| les trois suivants) : un premier déploiement a créé la table
| `tagtoa_payment_ledger_entries` puis échoué sur une étape SUIVANTE ; le
| script de déploiement désactive alors le module ENTIER sans jamais
| annuler la migration — chaque redéploiement suivant retombait sur
| « table already exists » et redésactivait Tagtoa (c'est ce que
| l'utilisateur voyait sur tagtoa.com : uniquement Biztap).
|
| Ce test rejoue EXACTEMENT ce scénario : la table existe déjà, on rappelle
| up() une 2e fois — ça ne doit jamais lancer d'exception.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\Tests\TestCase;

class MigrationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(string $file): object
    {
        return require __DIR__.'/../../Database/migrations/'.$file;
    }

    public function test_the_ledger_entries_migration_survives_a_table_that_already_exists(): void
    {
        $this->assertTrue(Schema::hasTable('tagtoa_payment_ledger_entries'));

        $migration = $this->runMigration('2026_10_15_000171_create_tagtoa_payment_ledger_entries_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('tagtoa_payment_ledger_entries'));
    }

    public function test_the_nfc_sun_columns_migration_survives_columns_that_already_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('tagtoa_ev_nfc_tags', 'sun_required'));
        $this->assertTrue(Schema::hasColumn('tagtoa_ev_nfc_tags', 'last_counter'));

        $migration = $this->runMigration('2026_10_16_000172_add_sun_verification_to_tagtoa_ev_nfc_tags.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('tagtoa_ev_nfc_tags', 'sun_required'));
        $this->assertTrue(Schema::hasColumn('tagtoa_ev_nfc_tags', 'last_counter'));
    }
}
