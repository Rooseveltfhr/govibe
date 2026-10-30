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

    public function test_the_deploy_script_runs_the_backup_before_maintenance_and_seeds_demo_only_on_request(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');

        $backup = strpos($sh, 'tagtoa:backup-db');
        $down   = strpos($sh, 'php artisan down');
        $migr   = strpos($sh, 'php artisan migrate --force');
        $this->assertNotFalse($backup);
        $this->assertLessThan($down, $backup, 'La sauvegarde doit précéder la maintenance.');
        $this->assertLessThan($migr, $backup);
        $this->assertStringContainsString('TAGTOA_BACKUP_REQUIRED', $sh);
        $this->assertStringContainsString('${TAGTOA_SEED_DEMO:-0}" = "1"', $sh, 'Le seed de démo doit être désactivé par défaut.');
    }
}
