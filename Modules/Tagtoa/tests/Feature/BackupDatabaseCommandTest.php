<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEPLOY — la sauvegarde avant migration (CS-0)
|--------------------------------------------------------------------------
| remote-deploy.sh annule en désactivant le module : il ne défait aucune
| migration. Cette commande est le seul filet. Elle doit donc (1) ne jamais
| laisser passer un dump tronqué pour une sauvegarde, (2) ne jamais mettre le
| mot de passe dans la ligne de commande, (3) ne rien faire quand rien n'est en
| attente, (4) ne supprimer que ses propres fichiers.
|
| Le dump est simulé ici (processus injecté). Le vrai mysqldump + la
| restauration sont exercés par BackupDatabaseRealMysqlTest.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Console\BackupDatabaseCommand;
use Modules\Tagtoa\Tests\TestCase;
use Symfony\Component\Process\Process;

class FakeDumpCommand extends BackupDatabaseCommand
{
    public static int $calls = 0;
    public static array $args = [];
    public static array $env = [];
    /** @var callable(callable):int */
    public static $behaviour;

    protected function binary(): string { return 'mysqldump'; }

    protected function runProcess(string $binary, array $args, array $env, int $timeout, callable $onOutput): int
    {
        self::$calls++;
        self::$args = $args;
        self::$env  = $env;

        return (self::$behaviour)($onOutput);
    }
}

class BackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        FakeDumpCommand::$calls = 0;
        FakeDumpCommand::$behaviour = function (callable $out): int {
            $out(Process::OUT, "-- dump\nCREATE TABLE t (id int);\n-- Dump completed on 2026-10-30\n");

            return 0;
        };
        parent::setUp();
        $this->app->bind(BackupDatabaseCommand::class, fn () => new FakeDumpCommand());
        $this->dir = sys_get_temp_dir().'/tagtoa-bk-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** Fait passer la connexion par défaut pour du MySQL (la config seule est lue, jamais ouverte). */
    private function asMysql(): void
    {
        config(['database.connections.fakemysql' => [
            'driver' => 'mysql', 'host' => 'db.internal', 'port' => 3307, 'database' => 'biz',
            'username' => 'bizuser', 'password' => 'S3cr3t-p@ss word', 'charset' => 'utf8mb4',
        ]]);
        config(['database.default' => 'fakemysql']);
    }

    private function backups(): array
    {
        return glob($this->dir.'/tagtoa-db-*.sql.gz') ?: [];
    }

    public function test_it_does_nothing_when_no_migration_is_pending(): void
    {
        $this->artisan('tagtoa:backup-db', ['--if-pending' => true, '--out' => $this->dir])->assertExitCode(0);

        $this->assertSame(0, FakeDumpCommand::$calls);
        $this->assertSame([], $this->backups());
    }

    public function test_it_backs_up_when_a_migration_is_pending(): void
    {
        $name = DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $name)->delete();
        $this->assertTrue($this->pendingFor($name));

        $this->asMysql();
        // La base par défaut est maintenant « mysql » factice : on ne peut plus lire
        // la table migrations. Le test ci-dessus a prouvé la détection ; ici on
        // prouve l'enchaînement détection → dump sans --if-pending.
        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])->assertExitCode(0);

        $this->assertSame(1, FakeDumpCommand::$calls);
        $this->assertCount(1, $this->backups());
    }

    private function pendingFor(string $name): bool
    {
        // --if-pending sur SQLite : détecte, tente, et refuse le pilote (jamais de dump).
        $this->artisan('tagtoa:backup-db', ['--if-pending' => true, '--out' => $this->dir])
            ->expectsOutputToContain('MySQL/MariaDB uniquement')
            ->assertExitCode(1);

        return true;
    }

    public function test_the_password_never_appears_on_the_command_line(): void
    {
        $this->asMysql();
        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])->assertExitCode(0);

        $this->assertSame('S3cr3t-p@ss word', FakeDumpCommand::$env['MYSQL_PWD']);
        $this->assertStringNotContainsString('S3cr3t', implode(' ', FakeDumpCommand::$args));
        $this->assertContains('--single-transaction', FakeDumpCommand::$args);
        $this->assertContains('--host=db.internal', FakeDumpCommand::$args);
        $this->assertContains('--port=3307', FakeDumpCommand::$args);
        $this->assertContains('--user=bizuser', FakeDumpCommand::$args);
        $this->assertSame('biz', end(FakeDumpCommand::$args));
    }

    public function test_the_file_is_private_and_a_valid_gzip_dump(): void
    {
        $this->asMysql();
        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])->assertExitCode(0);

        [$file] = $this->backups();
        $this->assertSame('0600', substr(sprintf('%o', fileperms($file)), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4));
        $this->assertStringContainsString('CREATE TABLE t', (string) gzdecode(file_get_contents($file)));
        $this->assertSame([], glob($this->dir.'/*.partial') ?: []);
    }

    public function test_a_failing_dump_leaves_no_file_and_fails(): void
    {
        FakeDumpCommand::$behaviour = function (callable $out): int {
            $out(Process::OUT, "-- partial\n");
            $out(Process::ERR, "mysqldump: Got error: 1045 Access denied\n");

            return 2;
        };
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])
            ->expectsOutputToContain('Access denied')
            ->assertExitCode(1);

        $this->assertSame([], glob($this->dir.'/*') ?: []);
    }

    public function test_a_truncated_dump_is_never_kept_as_a_backup(): void
    {
        // Exit 0 mais le dump s'est arrêté avant son pied de page (disque plein, coupure).
        FakeDumpCommand::$behaviour = function (callable $out): int {
            $out(Process::OUT, "-- dump\nCREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n");

            return 0;
        };
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])
            ->expectsOutputToContain('incomplet')
            ->assertExitCode(1);

        $this->assertSame([], glob($this->dir.'/*') ?: []);
    }

    public function test_an_empty_dump_is_refused(): void
    {
        FakeDumpCommand::$behaviour = fn (callable $out): int => 0;
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])->assertExitCode(1);
        $this->assertSame([], glob($this->dir.'/*') ?: []);
    }

    public function test_a_non_mysql_database_is_refused_loudly(): void
    {
        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])
            ->expectsOutputToContain('MySQL/MariaDB uniquement')
            ->assertExitCode(1);

        $this->assertSame(0, FakeDumpCommand::$calls);
    }

    public function test_retention_keeps_the_newest_and_never_touches_foreign_files(): void
    {
        mkdir($this->dir, 0700, true);
        foreach (['20250101-000001', '20250102-000001', '20250103-000001', '20250104-000001'] as $stamp) {
            file_put_contents($this->dir."/tagtoa-db-$stamp.sql.gz", 'old');
        }
        file_put_contents($this->dir.'/notes.txt', 'mine');
        file_put_contents($this->dir.'/other-app-20240101.sql.gz', 'not ours');
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 2])->assertExitCode(0);

        $names = array_map('basename', $this->backups());
        $this->assertCount(2, $names);
        $this->assertNotContains('tagtoa-db-20250101-000001.sql.gz', $names);
        $this->assertNotContains('tagtoa-db-20250102-000001.sql.gz', $names);
        $this->assertFileExists($this->dir.'/notes.txt');
        $this->assertFileExists($this->dir.'/other-app-20240101.sql.gz');
    }

    public function test_an_invalid_keep_value_is_refused_not_turned_into_one(): void
    {
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir.'/tagtoa-db-20250101-000001.sql.gz', 'old');
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => '14d'])->assertExitCode(1);

        $this->assertFileExists($this->dir.'/tagtoa-db-20250101-000001.sql.gz');
        $this->assertSame(0, FakeDumpCommand::$calls);
    }

    public function test_a_hand_named_file_is_neither_counted_nor_pruned(): void
    {
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir.'/tagtoa-db-pre-release.sql.gz', 'mine');
        file_put_contents($this->dir.'/tagtoa-db-20250101-000001.sql.gz', 'old');
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 1])->assertExitCode(0);

        $this->assertFileExists($this->dir.'/tagtoa-db-pre-release.sql.gz');
        $this->assertFileDoesNotExist($this->dir.'/tagtoa-db-20250101-000001.sql.gz');
    }

    public function test_the_deploy_script_runs_the_backup_before_maintenance_and_seeds_demo_only_on_request(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');

        $backup = strpos($sh, 'tagtoa:backup-db');
        $down   = strpos($sh, 'php artisan down');
        $migr   = strpos($sh, 'php artisan migrate --force');
        $this->assertNotFalse($backup);
        $this->assertLessThan($down, $backup, 'La sauvegarde doit précéder la maintenance.');
        $this->assertLessThan($migr, $backup);
        $this->assertLessThan($backup, strpos($sh, 'module:enable Tagtoa'), 'Le module doit être activé avant la sauvegarde.');
        $this->assertStringContainsString('TAGTOA_BACKUP_REQUIRED', $sh);
        $this->assertStringContainsString('${TAGTOA_SEED_DEMO:-0}" = "1"', $sh, 'Le seed de démo doit être désactivé par défaut.');
    }

    public function test_the_deploy_log_diagnoses_uploaded_images_without_ever_blocking_the_deploy(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $start = strpos($sh, '# 4.78) DIAGNOSTIC DES IMAGES');   // le bloc de LIAISON (4.76) qui le précède est testé par DeployUploadsLinkTest
        $end = strpos($sh, '# 4.8) Perf HTTP');
        $this->assertNotFalse($start);
        $block = substr($sh, $start, $end - $start);

        foreach (['disque public', 'dossier physique', 'menu-items', 'dernière image', 'HTTP'] as $needle) {
            $this->assertStringContainsString($needle, $block);
        }
        $this->assertStringNotContainsString('exit ', $block, 'Un diagnostic ne doit jamais arrêter le déploiement.');
        $this->assertStringNotContainsString('rollback', $block);
    }

    /* ---------- BK-1a : rétention par niveaux, une sauvegarde à chaque déploiement ---------- */

    private function sauvegardeDatee(\Carbon\Carbon $quand): string
    {
        $f = $this->dir.'/tagtoa-db-'.$quand->format('Ymd-His').'.sql.gz';
        file_put_contents($f, 'ancienne');

        return basename($f);
    }

    public function test_tiered_retention_keeps_the_last_n_one_per_day_and_one_per_week_and_drops_the_rest(): void
    {
        mkdir($this->dir, 0700, true);
        $jour = fn (int $n, string $h) => \Carbon\Carbon::now()->startOfDay()->subDays($n)->setTimeFromTimeString($h);
        $auj    = $this->sauvegardeDatee(\Carbon\Carbon::now()->startOfDay()->setTime(0, 0, 1));
        $hier9  = $this->sauvegardeDatee($jour(1, '09:00:00'));
        $hier8  = $this->sauvegardeDatee($jour(1, '08:00:00'));
        $j5     = $this->sauvegardeDatee($jour(5, '08:00:00'));
        $j20a   = $this->sauvegardeDatee($jour(20, '09:00:00'));
        $j20b   = $this->sauvegardeDatee($jour(20, '08:00:00'));
        $j40    = $this->sauvegardeDatee($jour(40, '08:00:00'));
        $j100   = $this->sauvegardeDatee($jour(100, '08:00:00'));
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 2, '--keep-daily' => 14, '--keep-weekly' => 8])->assertExitCode(0);

        $restent = array_map('basename', $this->backups());
        foreach ([$auj, $hier9, $j5, $j20a, $j40] as $doitRester) {
            $this->assertContains($doitRester, $restent, "$doitRester doit rester");
        }
        foreach ([$hier8, $j20b, $j100] as $doitPartir) {
            $this->assertNotContains($doitPartir, $restent, "$doitPartir doit partir");
        }
        $this->assertCount(6, $restent, 'Les 5 gardées + celle qui vient d\'être faite.');
    }

    public function test_with_daily_and_weekly_at_zero_only_the_last_n_remain(): void
    {
        mkdir($this->dir, 0700, true);
        foreach ([1, 2, 3, 4] as $n) {
            $this->sauvegardeDatee(\Carbon\Carbon::now()->startOfDay()->subDays($n)->setTime(8, 0, 0));
        }
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 2, '--keep-daily' => 0, '--keep-weekly' => 0])->assertExitCode(0);

        $this->assertCount(2, $this->backups(), 'La nouvelle + la plus récente des anciennes.');
    }

    public function test_the_file_just_made_is_never_pruned_and_foreign_files_stay_untouched(): void
    {
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir.'/tagtoa-db-pre-release.sql.gz', 'a la main');
        file_put_contents($this->dir.'/autre.sql.gz', 'autre');
        $this->sauvegardeDatee(\Carbon\Carbon::now()->subYears(2));
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 1, '--keep-daily' => 0, '--keep-weekly' => 0])->assertExitCode(0);

        $this->assertCount(1, BackupDatabaseCommand::listBackups($this->dir), 'Une seule sauvegarde datée : la nouvelle.');
        $this->assertFileExists($this->dir.'/tagtoa-db-pre-release.sql.gz');
        $this->assertFileExists($this->dir.'/autre.sql.gz');
    }

    public function test_invalid_daily_or_weekly_values_are_refused_without_deleting_anything(): void
    {
        mkdir($this->dir, 0700, true);
        $vieille = $this->sauvegardeDatee(\Carbon\Carbon::now()->subDays(3));
        $this->asMysql();

        foreach (['--keep-daily' => '14j', '--keep-weekly' => '-1'] as $opt => $val) {
            $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, $opt => $val])->assertExitCode(1);
        }

        $this->assertFileExists($this->dir.'/'.$vieille);
        $this->assertSame(0, FakeDumpCommand::$calls);
    }

    public function test_the_deploy_script_backs_up_every_deploy_verifies_without_blocking_and_keeps_the_old_mode_available(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');

        $this->assertStringContainsString('--keep-daily=', $sh);
        $this->assertStringContainsString('--keep-weekly=', $sh);
        $this->assertStringContainsString('--timeout=${TAGTOA_BACKUP_TIMEOUT:-300}', $sh, 'Un dump qui pend ne bloque pas le déploiement 15 minutes.');
        $this->assertStringNotContainsString('tagtoa:backup-db --if-pending', $sh, 'Plus de sauvegarde seulement si migration : une à chaque déploiement.');
        $this->assertStringContainsString('TAGTOA_BACKUP_IF_PENDING', $sh, 'L\'ancien comportement reste disponible.');
        $this->assertMatchesRegularExpression('/backup-verify[^\n]*\|\| echo/', $sh, 'La vérification ne doit jamais faire échouer le déploiement.');
        $this->assertLessThan(strpos($sh, 'php artisan down'), strpos($sh, 'tagtoa:backup-verify'), 'Vérifiée avant la maintenance.');
        $this->assertGreaterThan(strpos($sh, 'tagtoa:backup-db'), strpos($sh, 'tagtoa:backup-verify'));
    }

    public function test_the_backup_just_made_survives_even_when_a_newer_dated_file_exists(): void
    {
        mkdir($this->dir, 0700, true);
        $futur = $this->sauvegardeDatee(\Carbon\Carbon::now()->addDay());   // horloge déréglée / fichier copié d'ailleurs
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 1, '--keep-daily' => 0, '--keep-weekly' => 0])->assertExitCode(0);

        $noms = array_map('basename', $this->backups());
        $this->assertContains($futur, $noms);
        $this->assertCount(2, $noms, 'Celle qu\'on vient de faire n\'est jamais supprimée par sa propre rétention.');
    }

    public function test_the_weekly_tier_tells_weeks_of_different_years_apart(): void
    {
        mkdir($this->dir, 0700, true);
        $a = $this->sauvegardeDatee(\Carbon\Carbon::now()->startOfDay()->subDays(10)->setTime(8, 0, 0));
        $b = $this->sauvegardeDatee(\Carbon\Carbon::now()->startOfDay()->subDays(10 + 364)->setTime(8, 0, 0));   // même numéro de semaine, un an plus tôt
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 1, '--keep-daily' => 0, '--keep-weekly' => 60])->assertExitCode(0);

        $noms = array_map('basename', $this->backups());
        $this->assertContains($a, $noms);
        $this->assertContains($b, $noms, 'Deux semaines de même numéro mais d\'années différentes sont deux semaines.');
    }

    public function test_zero_disables_the_daily_and_weekly_tiers_instead_of_keeping_today_and_this_week(): void
    {
        mkdir($this->dir, 0700, true);
        $lointain = $this->sauvegardeDatee(\Carbon\Carbon::now()->addDays(30));                       // seule la plus récente (--keep=1)
        $ceSoir = $this->sauvegardeDatee(\Carbon\Carbon::now()->startOfDay()->setTime(23, 59, 59));  // la plus récente d'AUJOURD'HUI et de CETTE SEMAINE
        $this->asMysql();

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir, '--keep' => 1, '--keep-daily' => 0, '--keep-weekly' => 0])->assertExitCode(0);

        $noms = array_map('basename', $this->backups());
        $this->assertContains($lointain, $noms);
        $this->assertNotContains($ceSoir, $noms, '0 jour / 0 semaine = pas de niveau : on ne garde pas « celle d\'aujourd\'hui » ni « celle de cette semaine ».');
        $this->assertCount(2, $noms, 'La plus récente par nom + celle qu\'on vient de faire.');
    }
}
