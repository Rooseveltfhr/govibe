<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA SAUVEGARDE (BK-1b) — copie hors site chiffrée (R2 / B2 / S3)
|--------------------------------------------------------------------------
| Signature SigV4 vérifiée contre l'exemple OFFICIEL de la documentation AWS ; envoi vérifié contre un faux bucket (faux transport) :
| ce qui part est CHIFFRÉ (la clé privée, le mot de passe et le contenu en clair n'y figurent pas), relu, jamais renvoyé s'il est
| déjà là, et aucun secret n'apparaît dans la sortie.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Console\BackupOffsiteCommand;
use Modules\Tagtoa\App\Services\Backup\OffsiteCrypto;
use Modules\Tagtoa\App\Services\Backup\S3Client;
use Modules\Tagtoa\App\Services\Backup\S3Transport;
use Modules\Tagtoa\Tests\TestCase;
use Symfony\Component\Process\Process;

class BackupOffsiteCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private array $kp;
    private const SECRET = 'SECRET-ACCESS-KEY-do-not-leak-42';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/tagtoa-off-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        $this->kp = OffsiteCrypto::generateKeypair();
        $this->configure();
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();
        parent::tearDown();
    }

    private function configure(array $over = []): void
    {
        config(['tagtoa.offsite' => array_merge([
            'endpoint' => 'https://acct.r2.cloudflarestorage.com', 'bucket' => 'sauvegardes', 'key_id' => 'AKIDEXAMPLE',
            'secret' => self::SECRET, 'region' => 'auto', 'public_key' => $this->kp['public'], 'prefix' => 'tagtoa/',
        ], $over)]);
    }

    private function backups(): array
    {
        file_put_contents($this->dir.'/tagtoa-db-20261103-100000.sql.gz', gzencode("-- dump\nMOT-DE-PASSE-EN-CLAIR\n-- Dump completed\n"));
        file_put_contents($this->dir.'/tagtoa-uploads-20261103-100000.tar.gz', random_bytes(1000));

        return [$this->dir.'/tagtoa-db-20261103-100000.sql.gz', $this->dir.'/tagtoa-uploads-20261103-100000.tar.gz'];
    }

    private function run_(array $o = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('tagtoa:backup-offsite', array_merge(['--dir' => $this->dir], $o));
    }

    /** Faux bucket : mémorise les objets PUT, répond HEAD selon ce qu'il contient, journalise les requêtes. */
    private function fakeBucket(array &$store, bool $failPut = false, ?\Closure $head = null, ?string $putEtag = null): object
    {
        $t = new class($store, $failPut, $head, $putEtag) implements S3Transport {
            public array $log = [];
            public array $meta = [];
            public function __construct(private array &$store, private bool $failPut, private ?\Closure $head, private ?string $putEtag) {}
            public function request(string $method, string $url, array $headers, ?string $uploadFile, int $timeout): array
            {
                $key = parse_url($url, PHP_URL_PATH);
                $body = $uploadFile !== null ? (string) file_get_contents($uploadFile) : '';
                $this->log[] = compact('method', 'url', 'headers', 'body');
                if ($method === 'PUT') {
                    if ($this->failPut) {
                        return ['status' => 403, 'headers' => [], 'body' => '<Error>AccessDenied</Error>'];
                    }
                    $this->store[$key] = $body;
                    $this->meta[$key] = array_filter($headers, fn ($k) => str_starts_with($k, 'x-amz-meta-'), ARRAY_FILTER_USE_KEY);

                    return ['status' => 200, 'headers' => ['etag' => '"'.($this->putEtag ?? md5($body)).'"'], 'body' => ''];
                }
                if ($this->head) {
                    return ($this->head)($method, $key, $this->store);
                }
                if (isset($this->store[$key])) {
                    return ['status' => 200, 'headers' => ['content-length' => (string) strlen($this->store[$key]), 'etag' => '"'.md5($this->store[$key]).'"'] + ($this->meta[$key] ?? []), 'body' => ''];
                }

                return ['status' => 404, 'headers' => [], 'body' => ''];
            }
        };
        $this->app->instance(S3Transport::class, $t);

        return $t;
    }

    // ── signature ───────────────────────────────────────────────────────────────

    public function test_sigv4_matches_the_official_aws_documentation_example(): void
    {
        $c = new S3Client('https://examplebucket.s3.amazonaws.com', 'examplebucket', 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY', 'us-east-1');
        $h = $c->sign('GET', '/test.txt', hash('sha256', ''), new \DateTimeImmutable('2013-05-24T00:00:00Z'), ['Range' => 'bytes=0-9']);

        $this->assertStringContainsString('Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41', $h['authorization']);
        $this->assertStringContainsString('Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request', $h['authorization']);
        $this->assertStringContainsString('SignedHeaders=host;range;x-amz-content-sha256;x-amz-date', $h['authorization']);
    }

    public function test_object_paths_are_encoded_once_and_keep_their_slashes(): void
    {
        $c = new S3Client('https://x.example.com', 'mon bucket', 'k', 's');
        $this->assertSame('/mon%20bucket/tagtoa/a%20b%2Bc.enc', $c->objectPath('tagtoa/a b+c.enc'));
    }

    public function test_a_non_https_or_pathful_endpoint_is_refused(): void
    {
        foreach (['http://x.example.com', 'https://x.example.com/bucket', 'x.example.com'] as $bad) {
            try {
                new S3Client($bad, 'b', 'k', 's');
                $this->fail('Point d\'accès accepté : '.$bad);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ── configuration ───────────────────────────────────────────────────────────

    public function test_unconfigured_is_a_calm_skip_not_an_error(): void
    {
        config(['tagtoa.offsite' => []]);
        $store = []; $t = $this->fakeBucket($store);
        $this->run_()->expectsOutputToContain('non configurée')->assertExitCode(0);
        $this->assertSame([], $t->log);
    }

    public function test_half_configured_fails_and_names_the_missing_variable_without_leaking_values(): void
    {
        $this->configure(['bucket' => '']);
        $store = []; $t = $this->fakeBucket($store);
        $this->run_()->expectsOutputToContain('TAGTOA_OFFSITE_BUCKET')->assertExitCode(1);
        $this->assertSame([], $t->log);
    }

    public function test_an_invalid_public_key_or_endpoint_fails_before_any_network_call(): void
    {
        $store = []; $t = $this->fakeBucket($store);
        $this->configure(['public_key' => 'pas-une-cle']);
        $this->run_()->expectsOutputToContain('configuration invalide')->assertExitCode(1);
        $this->configure(['endpoint' => 'http://clair.example.com']);
        $this->run_()->expectsOutputToContain('configuration invalide')->assertExitCode(1);
        $this->assertSame([], $t->log);
    }

    // ── envoi ───────────────────────────────────────────────────────────────────

    public function test_both_latest_backups_are_encrypted_uploaded_and_verified(): void
    {
        [$db, $up] = $this->backups();
        $store = [];
        $this->fakeBucket($store);

        $this->run_()->expectsOutputToContain('Hors site OK : tagtoa/tagtoa-db-20261103-100000.sql.gz.enc')
            ->expectsOutputToContain('Hors site OK : tagtoa/tagtoa-uploads-20261103-100000.tar.gz.enc')
            ->assertExitCode(0);

        $this->assertCount(2, $store);
        $objet = $store['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc'];
        $this->assertStringNotContainsString('MOT-DE-PASSE-EN-CLAIR', $objet);
        $this->assertStringNotContainsString($this->kp['private'], $objet);
        $this->assertSame(OffsiteCrypto::encryptedSize(filesize($db)), strlen($objet));

        // Et avec la clé privée, on retrouve exactement la sauvegarde.
        $enc = $this->dir.'/r.enc';
        file_put_contents($enc, $objet);
        OffsiteCrypto::decryptFile($enc, $this->dir.'/r.dec', OffsiteCrypto::privateKeyFromBase64($this->kp['private']));
        $this->assertSame(file_get_contents($db), file_get_contents($this->dir.'/r.dec'));
        $this->assertSame([], glob($this->dir.'/.offsite-*.tmp') ?: [], 'Aucune copie chiffrée temporaire ne reste sur le disque.');
    }

    public function test_requests_are_signed_and_carry_the_payload_hash_but_never_the_secret(): void
    {
        $this->backups();
        $store = [];
        $t = $this->fakeBucket($store);
        $this->run_()->assertExitCode(0);

        $this->assertNotEmpty($t->log);
        foreach ($t->log as $r) {
            $auth = $r['headers']['authorization'] ?? '';
            $this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/', $auth);
            $this->assertStringNotContainsString(self::SECRET, $auth.$r['url'].json_encode($r['headers']));
            if ($r['method'] === 'PUT') {
                $this->assertSame(hash('sha256', $r['body']), $r['headers']['x-amz-content-sha256']);
            }
        }
    }

    public function test_a_file_already_there_at_the_right_size_is_not_sent_again(): void
    {
        [$db, $up] = $this->backups();
        $store = [
            '/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc' => str_repeat('x', OffsiteCrypto::encryptedSize(filesize($db))),
            '/sauvegardes/tagtoa/tagtoa-uploads-20261103-100000.tar.gz.enc' => str_repeat('y', OffsiteCrypto::encryptedSize(filesize($up))),
        ];
        $t = $this->fakeBucket($store);
        $t->meta = [
            '/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc' => ['x-amz-meta-tagtoa-sha256' => hash_file('sha256', $db)],
            '/sauvegardes/tagtoa/tagtoa-uploads-20261103-100000.tar.gz.enc' => ['x-amz-meta-tagtoa-sha256' => hash_file('sha256', $up)],
        ];

        $this->run_()->expectsOutputToContain('Déjà hors site')->assertExitCode(0);
        $this->assertSame([], array_filter($t->log, fn ($r) => $r['method'] === 'PUT'));
    }

    public function test_a_wrong_size_object_is_replaced(): void
    {
        $this->backups();
        $store = ['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc' => 'tronqué'];
        $this->fakeBucket($store);

        $this->run_()->assertExitCode(0);
        $this->assertNotSame('tronqué', $store['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc']);
    }

    public function test_a_refused_upload_fails_without_leaking_secrets_or_leaving_temp_files(): void
    {
        $this->backups();
        $store = [];
        $this->fakeBucket($store, failPut: true);

        $this->run_()->expectsOutputToContain('Envoi refusé (HTTP 403)')->assertExitCode(1);
        $this->assertSame([], glob($this->dir.'/.offsite-*.tmp') ?: []);
    }

    public function test_a_wrong_size_on_readback_is_reported(): void
    {
        $this->backups();
        $store = [];
        $n = 0;
        $this->fakeBucket($store, head: function ($m, $key, $st) use (&$n) {
            return $n++ % 2 === 0 ? ['status' => 404, 'headers' => [], 'body' => ''] : ['status' => 200, 'headers' => ['content-length' => '9999999', 'etag' => '"x"'], 'body' => ''];
        });

        $this->run_()->expectsOutputToContain('mauvaise taille')->assertExitCode(1);
    }

    public function test_a_different_etag_on_upload_is_reported_as_a_corrupted_copy(): void
    {
        $this->backups();
        $store = [];
        $this->fakeBucket($store, putEtag: str_repeat('a', 32));

        $this->run_()->expectsOutputToContain('empreinte différente')->assertExitCode(1);
    }

    public function test_the_latest_local_backup_is_the_one_sent_and_old_ones_are_not(): void
    {
        $this->backups();
        file_put_contents($this->dir.'/tagtoa-db-20250101-000000.sql.gz', gzencode("-- Dump completed\n"));
        $store = [];
        $this->fakeBucket($store);

        $this->run_()->assertExitCode(0);
        $this->assertArrayNotHasKey('/sauvegardes/tagtoa/tagtoa-db-20250101-000000.sql.gz.enc', $store);
    }

    public function test_nothing_to_send_is_an_error_not_a_silent_success(): void
    {
        $store = []; $this->fakeBucket($store);
        $this->run_()->expectsOutputToContain('Aucune sauvegarde locale')->assertExitCode(1);
    }

    public function test_not_enough_disk_for_the_temporary_encrypted_copy_fails_cleanly(): void
    {
        $this->backups();
        $store = [];
        $this->fakeBucket($store);
        $this->app->bind(BackupOffsiteCommand::class, fn () => new class extends BackupOffsiteCommand {
            protected function freeBytes(string $dir): float|false { return 1.0; }
        });

        $this->run_()->expectsOutputToContain('place disque insuffisante')->assertExitCode(1);
        $this->assertSame([], $store);
    }

    public function test_a_second_run_while_one_is_in_progress_is_skipped(): void
    {
        $this->backups();
        $h = fopen($this->dir.'/.tagtoa-offsite.lock', 'c');
        $this->assertTrue(flock($h, LOCK_EX | LOCK_NB));
        $store = []; $t = $this->fakeBucket($store);

        $this->run_()->expectsOutputToContain('déjà en cours')->assertExitCode(0);
        $this->assertSame([], $t->log);
        flock($h, LOCK_UN);
        fclose($h);
    }

    // ── clés, déchiffrement, déploiement ────────────────────────────────────────

    public function test_keygen_prints_a_working_pair_and_stores_nothing(): void
    {
        $this->artisan('tagtoa:backup-keygen')->expectsOutputToContain('CLÉ PUBLIQUE')->expectsOutputToContain('CLÉ PRIVÉE')->assertExitCode(0);
    }

    public function test_decrypt_command_restores_a_file_with_the_private_key_from_the_environment_and_never_overwrites(): void
    {
        $src = $this->dir.'/x.sql.gz';
        file_put_contents($src, 'contenu');
        OffsiteCrypto::encryptFile($src, $this->dir.'/x.sql.gz.enc', OffsiteCrypto::publicKeyFromBase64($this->kp['public']));
        putenv('TAGTOA_OFFSITE_PRIVATE_KEY='.$this->kp['private']);
        try {
            $out = $this->dir.'/restaure.sql.gz';
            $this->artisan('tagtoa:backup-decrypt', ['file' => $this->dir.'/x.sql.gz.enc', '--out' => $out])->assertExitCode(0);
            $this->assertSame('contenu', file_get_contents($out));

            $this->artisan('tagtoa:backup-decrypt', ['file' => $this->dir.'/x.sql.gz.enc', '--out' => $out])
                ->expectsOutputToContain('rien n\'est écrasé')->assertExitCode(1);

            $autre = OffsiteCrypto::generateKeypair();
            putenv('TAGTOA_OFFSITE_PRIVATE_KEY='.$autre['private']);
            $this->artisan('tagtoa:backup-decrypt', ['file' => $this->dir.'/x.sql.gz.enc', '--out' => $this->dir.'/non.out'])
                ->expectsOutputToContain('Clé privée incorrecte')->assertExitCode(1);
            $this->assertFileDoesNotExist($this->dir.'/non.out');
        } finally {
            putenv('TAGTOA_OFFSITE_PRIVATE_KEY');
        }
    }

    public function test_the_deploy_script_sends_offsite_after_the_local_backups_and_never_blocks_by_default(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $off = strpos($sh, 'php artisan tagtoa:backup-offsite');
        $this->assertNotFalse($off);
        $this->assertStringContainsString('php artisan tagtoa:backup-offsite $OFFSITE_ARGS', $sh);
        $this->assertStringContainsString('--max-age-db=30', $sh, 'La base envoyée doit dater de ce déploiement.');
        $this->assertStringContainsString('timeout -k 30 $(( OFFSITE_T * 2 + 300 ))', $sh, 'Le délai global couvre les deux envois ; -k tue PHP s\'il ignore le SIGTERM.');
        $this->assertGreaterThan(strpos($sh, 'php artisan tagtoa:backup-uploads'), $off);
        $this->assertLessThan(strpos($sh, 'php artisan down'), $off);
        $this->assertStringContainsString('TAGTOA_OFFSITE_SKIP', $sh);
        $this->assertMatchesRegularExpression('/COPIE HORS SITE IMPOSSIBLE.*?TAGTOA_OFFSITE_REQUIRED/s', $sh);
    }

    public function test_same_size_but_different_content_is_not_taken_for_already_sent(): void
    {
        [$db] = $this->backups();
        $store = ['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc' => str_repeat('x', OffsiteCrypto::encryptedSize(filesize($db)))];
        $t = $this->fakeBucket($store);
        $t->meta = ['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc' => ['x-amz-meta-tagtoa-sha256' => str_repeat('0', 64)]];   // même poids, autre contenu

        $this->run_()->assertExitCode(0);
        $puts = array_filter($t->log, fn ($r) => $r['method'] === 'PUT' && str_contains($r['url'], 'tagtoa-db-'));
        $this->assertCount(1, $puts, 'L\'objet suspect est renvoyé, pas validé sur sa seule taille.');
        $this->assertNotSame(str_repeat('x', strlen($store['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc'])), $store['/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc']);
    }

    public function test_the_content_hash_travels_as_signed_object_metadata(): void
    {
        [$db] = $this->backups();
        $store = [];
        $t = $this->fakeBucket($store);
        $this->run_()->assertExitCode(0);

        $put = array_values(array_filter($t->log, fn ($r) => $r['method'] === 'PUT' && str_contains($r['url'], 'tagtoa-db-')))[0];
        $this->assertSame(hash_file('sha256', $db), $put['headers']['x-amz-meta-tagtoa-sha256']);
        $this->assertStringContainsString('x-amz-meta-tagtoa-sha256', $put['headers']['authorization'], 'La métadonnée fait partie de la signature.');
    }

    public function test_a_stale_database_backup_is_refused_but_the_images_are_still_sent(): void
    {
        $this->backups();   // dump daté de 2026-11-03 10:00:00
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03 18:00:00'));
        $store = [];
        $t = $this->fakeBucket($store);

        $this->run_(['--max-age-db' => 30])->expectsOutputToContain('date de plus de 30 min')->assertExitCode(1);

        $this->assertArrayNotHasKey('/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc', $store, 'Une vieille sauvegarde n\'est pas présentée comme fraîche.');
        $this->assertArrayHasKey('/sauvegardes/tagtoa/tagtoa-uploads-20261103-100000.tar.gz.enc', $store, 'Les images, inchangées, peuvent légitimement dater.');
    }

    public function test_a_fresh_database_backup_passes_the_age_check(): void
    {
        $this->backups();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03 10:10:00'));
        $store = [];
        $this->fakeBucket($store);
        $this->run_(['--max-age-db' => 30])->assertExitCode(0);
    }

    public function test_orphan_temporary_encrypted_copies_are_swept_but_recent_and_foreign_files_are_not(): void
    {
        $this->backups();
        $vieux = $this->dir.'/.offsite-deadbeef.tmp';
        $recent = $this->dir.'/.offsite-cafebabe.tmp';
        $autre = $this->dir.'/.offsite-pas-a-moi.tmp';
        foreach ([$vieux, $recent, $autre] as $f) { file_put_contents($f, 'x'); }
        touch($vieux, time() - 7200);
        touch($autre, time() - 7200);
        $store = [];
        $this->fakeBucket($store);

        $this->run_(['--timeout' => 60])->assertExitCode(0);

        $this->assertFileDoesNotExist($vieux, 'Un envoi tué net laisse ≈ la taille de la sauvegarde : sinon le disque se remplit à chaque déploiement raté.');
        $this->assertFileExists($recent);
        $this->assertFileExists($autre);
    }

    public function test_error_bodies_are_never_echoed_only_the_error_code(): void
    {
        $this->backups();
        $store = [];
        $this->app->instance(S3Transport::class, new class implements S3Transport {
            public function request(string $method, string $url, array $headers, ?string $uploadFile, int $timeout): array
            {
                return $method === 'PUT'
                    ? ['status' => 403, 'headers' => [], 'body' => '<Error><Code>SignatureDoesNotMatch</Code><AWSAccessKeyId>AKIDEXAMPLE</AWSAccessKeyId><CanonicalRequest>PUT /secret-bucket</CanonicalRequest></Error>']
                    : ['status' => 404, 'headers' => [], 'body' => ''];
            }
        });

        // Deux passages : une attente « contient » consomme la ligne, la vérification d'absence ne la reverrait plus.
        $this->run_()->expectsOutputToContain('SignatureDoesNotMatch')->assertExitCode(1);
        $this->run_()->doesntExpectOutputToContain('AKIDEXAMPLE')->doesntExpectOutputToContain('secret-bucket')->doesntExpectOutputToContain('CanonicalRequest')->assertExitCode(1);
    }

    public function test_dotenv_options_reach_the_deploy_script_but_a_real_environment_variable_wins(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $a = strpos($sh, '# >>> TAGTOA_DOTENV_OPTIONS');
        $b = strpos($sh, '# <<< TAGTOA_DOTENV_OPTIONS');
        $this->assertNotFalse($a);
        $bloc = substr($sh, $a, $b - $a);
        file_put_contents($this->dir.'/.env', "APP_KEY=base64:abc\nTAGTOA_OFFSITE_REQUIRED=1\nTAGTOA_BACKUP_KEEP=\"7\"\r\nTAGTOA_OFFSITE_SKIP=0\nTAGTOA_OFFSITE_SECRET=ne-doit-pas-sortir\n");
        file_put_contents($this->dir.'/t.sh', "set -uo pipefail\ncd ".escapeshellarg($this->dir)."\n".$bloc."\necho \"R=\${TAGTOA_OFFSITE_REQUIRED:-}|K=\${TAGTOA_BACKUP_KEEP:-}|S=\${TAGTOA_OFFSITE_SKIP:-}|X=\${TAGTOA_OFFSITE_SECRET:-}\"\n");

        $p = new Process(['bash', $this->dir.'/t.sh']);
        $p->mustRun();
        $this->assertSame("R=1|K=7|S=0|X=\n", $p->getOutput(), 'Valeurs lues dans le .env (guillemets et retour chariot retirés) ; une variable hors liste n\'est jamais exportée.');

        $p = new Process(['bash', $this->dir.'/t.sh'], null, ['TAGTOA_OFFSITE_REQUIRED' => '0', 'PATH' => getenv('PATH')]);
        $p->mustRun();
        $this->assertStringStartsWith('R=0|', $p->getOutput(), 'L\'environnement du shell a priorité sur le .env.');
    }

    /** Vrai cURL, vrai serveur HTTP local : prouve le flux PUT/HEAD réel (pas seulement le faux transport). */
    public function test_end_to_end_with_real_curl_against_a_local_http_server(): void
    {
        if (! function_exists('curl_init')) {
            $this->markTestSkipped('ext-curl absente.');
        }
        $this->backups();
        $srv = $this->dir.'/srv';
        mkdir($srv);
        file_put_contents($srv.'/router.php', <<<'PHP'
<?php
$root = __DIR__.'/objets';
@mkdir($root);
$f = $root.'/'.md5($_SERVER['REQUEST_URI']);
$m = $_SERVER['REQUEST_METHOD'];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (! str_starts_with($auth, 'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/')) { http_response_code(403); exit; }
if ($m === 'PUT') {
    $body = file_get_contents('php://input');
    if (hash('sha256', $body) !== ($_SERVER['HTTP_X_AMZ_CONTENT_SHA256'] ?? '')) { http_response_code(400); exit('BadDigest'); }
    file_put_contents($f, $body);
    file_put_contents($f.'.uri', $_SERVER['REQUEST_URI']);
    file_put_contents($f.'.meta', $_SERVER['HTTP_X_AMZ_META_TAGTOA_SHA256'] ?? '');
    file_put_contents($f.'.expect', $_SERVER['HTTP_EXPECT'] ?? '');
    header('ETag: "'.md5($body).'"');
    exit;
}
if ($m === 'HEAD') {
    if (! is_file($f)) { http_response_code(404); exit; }
    header('Content-Length: '.filesize($f));
    header('ETag: "'.md5_file($f).'"');
    if (is_file($f.'.meta')) { header('x-amz-meta-tagtoa-sha256: '.file_get_contents($f.'.meta')); }
    exit;
}
http_response_code(405);
PHP);
        $port = random_int(20000, 40000);
        $proc = new Process([PHP_BINARY, '-S', "127.0.0.1:$port", $srv.'/router.php']);
        $proc->start();
        try {
            $deadline = microtime(true) + 5;
            while (microtime(true) < $deadline && @fsockopen('127.0.0.1', $port) === false) {
                usleep(50000);
            }
            $this->configure(['endpoint' => "http://127.0.0.1:$port"]);
            $this->app->forgetInstance(S3Transport::class);
            $this->app->offsetUnset(S3Transport::class);

            file_put_contents($this->dir.'/tagtoa-uploads-20261103-100000.tar.gz', random_bytes(2 * 1048576));   // > 1 Mo : cURL ajouterait « Expect: 100-continue »
            $this->run_()->expectsOutputToContain('Hors site OK')->assertExitCode(0);
            $this->assertSame([''], array_unique(array_map('file_get_contents', glob($srv.'/objets/*.expect') ?: [])), 'Aucun en-tête Expect envoyé.');
            $objets = glob($srv.'/objets/*.uri') ?: [];
            $this->assertCount(2, $objets, 'Les deux sauvegardes sont arrivées au serveur.');
            $this->assertStringContainsString('/sauvegardes/tagtoa/tagtoa-db-20261103-100000.sql.gz.enc', implode(' ', array_map('file_get_contents', $objets)));

            // Deuxième passage : rien n'est renvoyé.
            $this->run_()->expectsOutputToContain('Déjà hors site')->assertExitCode(0);
        } finally {
            $proc->stop();
        }
    }
}
