<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEPLOY — vrai mysqldump + vraie restauration (CS-0)
|--------------------------------------------------------------------------
| Une sauvegarde qu'on n'a jamais restaurée n'est pas une sauvegarde. Ce test
| fait le trajet complet : dump réel -> vérification -> restauration dans une
| autre base -> comparaison des données.
|
| Ignoré tant que TAGTOA_TEST_MYSQL_* n'est pas fourni (la CI actuelle n'a pas
| de serveur MySQL) :
|   TAGTOA_TEST_MYSQL_HOST, _PORT, _USER, _PASS, _DB (source), _DB_RESTORE (cible)
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;
use Symfony\Component\Process\Process;

class BackupDatabaseRealMysqlTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private array $c;
    private string $originalDefault = 'testbench';

    protected function setUp(): void
    {
        parent::setUp();
        $e = fn (string $k, $d = null) => getenv('TAGTOA_TEST_MYSQL_'.$k) ?: $d;
        $this->c = ['host' => $e('HOST'), 'port' => $e('PORT', '3306'), 'user' => $e('USER'),
                    'pass' => (string) $e('PASS', ''), 'db' => $e('DB'), 'restore' => $e('DB_RESTORE')];
        if (! $this->c['host'] || ! $this->c['user'] || ! $this->c['db'] || ! $this->c['restore']) {
            $this->markTestSkipped('TAGTOA_TEST_MYSQL_* absent : test MySQL réel ignoré.');
        }
        $this->originalDefault = config('database.default');
        $this->dir = sys_get_temp_dir().'/tagtoa-real-'.bin2hex(random_bytes(4));
        $this->useConnection($this->c['db'], $this->c['pass']);
    }

    protected function tearDown(): void
    {
        // Testbench défait ses migrations sur la connexion PAR DÉFAUT : la remettre
        // avant, sinon il viserait la vraie base MySQL.
        config(['database.default' => $this->originalDefault]);
        foreach (glob(($this->dir ?? '/nonexistent').'/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir ?? '/nonexistent');
        parent::tearDown();
    }

    private function useConnection(string $db, string $pass): void
    {
        config(['database.connections.realmysql' => [
            'driver' => 'mysql', 'host' => $this->c['host'], 'port' => $this->c['port'], 'database' => $db,
            'username' => $this->c['user'], 'password' => $pass, 'charset' => 'utf8mb4',
        ], 'database.default' => 'realmysql']);
        \DB::purge('realmysql');
    }

    private function sql(string $db, string $sql): void
    {
        $p = new Process(['mysql', '-h', $this->c['host'], '-P', $this->c['port'], '-u', $this->c['user'], $db, '-e', $sql],
            null, ['MYSQL_PWD' => $this->c['pass']]);
        $p->mustRun();
    }

    private function scalar(string $db, string $sql): string
    {
        $p = new Process(['mysql', '-N', '-B', '-h', $this->c['host'], '-P', $this->c['port'], '-u', $this->c['user'], $db, '-e', $sql],
            null, ['MYSQL_PWD' => $this->c['pass']]);
        $p->mustRun();

        return trim($p->getOutput());
    }

    public function test_dump_restores_into_another_database_with_identical_data(): void
    {
        foreach ([$this->c['db'], $this->c['restore']] as $db) {
            $this->sql($db, 'DROP TABLE IF EXISTS ledger_t; DROP TABLE IF EXISTS parent_t;');
        }
        $this->sql($this->c['db'], "
            CREATE TABLE parent_t (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(80)) ENGINE=InnoDB;
            CREATE TABLE ledger_t (id INT PRIMARY KEY AUTO_INCREMENT, parent_id INT NOT NULL, amount DECIMAL(14,2) NOT NULL,
              note TEXT, FOREIGN KEY (parent_id) REFERENCES parent_t(id)) ENGINE=InnoDB;
            INSERT INTO parent_t (name) VALUES ('Café Ti Jean'), ('L''Étoile \"Bleue\"'), ('日本語');
            INSERT INTO ledger_t (parent_id, amount, note) VALUES (1, 1250.50, 'vente; DROP TABLE x;--'), (2, -3.07, NULL), (3, 99999999999.99, 'ligne\nmulti');");

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])->assertExitCode(0);

        [$file] = glob($this->dir.'/tagtoa-db-*.sql.gz');
        $this->assertSame('0600', substr(sprintf('%o', fileperms($file)), -4));

        // Restauration réelle, comme documentée dans DEPLOY.md.
        $r = Process::fromShellCommandline(
            'gunzip -c '.escapeshellarg($file).' | mysql -h '.escapeshellarg($this->c['host']).' -P '.escapeshellarg($this->c['port'])
            .' -u '.escapeshellarg($this->c['user']).' '.escapeshellarg($this->c['restore']),
            null, ['MYSQL_PWD' => $this->c['pass']]);
        $r->mustRun();

        foreach (['parent_t', 'ledger_t'] as $t) {
            $this->assertSame(
                $this->scalar($this->c['db'], "CHECKSUM TABLE $t"),
                str_replace($this->c['restore'].'.', $this->c['db'].'.', $this->scalar($this->c['restore'], "CHECKSUM TABLE $t")),
                "La table $t restaurée diffère de l'originale.");
        }
        $this->assertSame('3', $this->scalar($this->c['restore'], 'SELECT COUNT(*) FROM ledger_t'));
        $this->assertSame('99999999999.99', $this->scalar($this->c['restore'], 'SELECT MAX(amount) FROM ledger_t'));
        $this->assertSame('日本語', $this->scalar($this->c['restore'], 'SELECT name FROM parent_t WHERE id=3'));
    }

    public function test_a_wrong_password_fails_and_leaves_no_file(): void
    {
        $this->useConnection($this->c['db'], $this->c['pass'].'-faux');

        $this->artisan('tagtoa:backup-db', ['--out' => $this->dir])->assertExitCode(1);

        $this->assertSame([], glob($this->dir.'/*') ?: []);
    }
}
