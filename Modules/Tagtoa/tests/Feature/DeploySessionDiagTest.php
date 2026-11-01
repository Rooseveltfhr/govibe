<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEPLOY — bloc 5.9 « Session / connexion (419) »
|--------------------------------------------------------------------------
| « Page expirée » à chaque connexion admin. Le bloc recrée les dossiers de
| session manquants (seule réparation automatique), puis rejoue un VRAI
| parcours de connexion sur le serveur (GET /login → jeton → POST d'un compte
| inexistant) et dit si la session tient (419 = cassée). Il ne doit jamais
| bloquer le déploiement, ni afficher une valeur de cookie.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;
use Symfony\Component\Process\Process;

class DeploySessionDiagTest extends TestCase
{
    use RefreshDatabase;

    private string $t;

    protected function setUp(): void
    {
        parent::setUp();
        $this->t = sys_get_temp_dir().'/tagtoa-419-'.bin2hex(random_bytes(4));
        mkdir($this->t.'/app/storage/logs', 0777, true);
        mkdir($this->t.'/bin', 0777, true);
        file_put_contents($this->t.'/app/.env', "APP_URL=https://tagtoa.test\n");
        file_put_contents($this->t.'/app/storage/logs/laravel.log',
            "[2026-11-01 01:00:00] production.ERROR: CSRF token mismatch. {\"x\":1}\n#0 trace\n");
        // Faux php : la configuration de session telle que le vrai script l'imprime.
        file_put_contents($this->t.'/bin/php', "#!/usr/bin/env bash\necho '   config : driver=file domain=NULL secure=true same_site=lax path=/ lifetime=120 cookie=biztap_session'\n");
        chmod($this->t.'/bin/php', 0755);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->t]))->run();
        parent::tearDown();
    }

    /** Faux curl : page de connexion (formulaire → $action) au GET, code $postCode au POST. */
    private function fakeCurl(string $postCode, string $action = '/login'): void
    {
        $sh = <<<SH
#!/usr/bin/env bash
args="\$*"
if [[ "\$args" == *"--data-urlencode"* ]]; then echo "\$args" > "\$(dirname "\$0")/../posted"; printf '%s' '$postCode'; exit 0; fi
if [[ "\$args" == *" -D -"* ]]; then printf 'HTTP/1.1 200 OK\r\nSet-Cookie: biztap_session=SECRETVALUE123; path=/; secure; httponly; samesite=lax\r\n\r\n'; exit 0; fi
if [[ "\$args" == *"/admin/login"* ]]; then printf '<p>non</p>\n__FINAL__ 404 https://tagtoa.test/admin/login'; exit 0; fi
if [[ "\$args" == *"/login"* ]]; then printf '<form method="POST" action="$action"><input type="hidden" name="_token" value="tok123" autocomplete="off"></form>\n__FINAL__ 200 https://tagtoa.test/fr/login'; fi
exit 0
SH;
        file_put_contents($this->t.'/bin/curl', $sh);
        chmod($this->t.'/bin/curl', 0755);
    }

    private function run59(): Process
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $a = strpos($sh, '# 5.9) SESSION');
        $b = strpos($sh, '# 6) Smoke test public');
        $this->assertNotFalse($a, 'Bloc 5.9 absent du script de déploiement.');
        file_put_contents($this->t.'/block.sh', "set -uo pipefail\n".substr($sh, $a, $b - $a)."echo FIN_BLOC\n");

        $p = Process::fromShellCommandline('bash '.escapeshellarg($this->t.'/block.sh'), $this->t.'/app', [
            'PATH' => $this->t.'/bin:'.getenv('PATH'),
        ]);
        $p->setTimeout(60)->run();

        return $p;
    }

    public function test_missing_session_folders_are_recreated_and_the_deploy_continues(): void
    {
        $this->fakeCurl('302');
        $p = $this->run59();
        $out = $p->getOutput();

        $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput());
        $this->assertStringContainsString('FIN_BLOC', $out, 'Le bloc ne doit jamais interrompre le déploiement.');
        foreach (['sessions', 'cache/data', 'views'] as $d) {
            $this->assertDirectoryExists($this->t.'/app/storage/framework/'.$d);
        }
        $this->assertStringContainsString('CRÉÉ : storage/framework/sessions', $out);
        $this->assertStringContainsString('écriture réelle : OK', $out);
    }

    public function test_a_working_session_is_reported_ok(): void
    {
        $this->fakeCurl('302');
        $out = $this->run59()->getOutput();

        $this->assertStringContainsString('GET /login → 200 https://tagtoa.test/fr/login ; formulaire → https://tagtoa.test/login', $out);
        $this->assertStringContainsString('POST → 302 — session OK', $out);
        $this->assertStringContainsString('GET /admin/login → 404 https://tagtoa.test/admin/login : pas de formulaire', $out);
        $this->assertStringNotContainsString('ATTENTION', $out);
        $this->assertStringContainsString('_token=tok123', (string) file_get_contents($this->t.'/posted'), 'Le jeton lu est bien renvoyé.');
        $this->assertStringContainsString('cookie=biztap_session', $out);
    }

    public function test_a_broken_session_is_reported_as_419_without_leaking_cookie_values(): void
    {
        $this->fakeCurl('419');
        $out = $this->run59()->getOutput();

        $this->assertStringContainsString('POST → 419 PAGE EXPIRÉE', $out);
        $this->assertStringContainsString('secure', $out, 'Les drapeaux du cookie sont affichés…');
        $this->assertStringNotContainsString('SECRETVALUE123', $out, '…jamais sa valeur.');
        $this->assertStringContainsString('419 dans laravel.log : 1', $out);
        $this->assertStringContainsString('production.ERROR: CSRF token mismatch.', $out);
    }

    public function test_a_form_posting_to_another_host_is_flagged(): void
    {
        $this->fakeCurl('419', 'https://www.tagtoa.test/login');
        $out = $this->run59()->getOutput();

        $this->assertStringContainsString('formulaire → https://www.tagtoa.test/login', $out);
        $this->assertStringContainsString('servie sur tagtoa.test mais le formulaire poste vers www.tagtoa.test', $out);
        $this->assertStringContainsString('https://www.tagtoa.test/login', (string) file_get_contents($this->t.'/posted'), 'Le POST suit l\'action réelle du formulaire.');
    }

    public function test_a_form_posting_over_plain_http_is_flagged(): void
    {
        $this->fakeCurl('419', 'http://tagtoa.test/login');
        $this->assertStringContainsString('poste en http://', $this->run59()->getOutput());
    }

    public function test_the_block_runs_after_the_site_is_back_up_and_deletes_nothing(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $a = strpos($sh, '# 5.9) SESSION');
        $this->assertGreaterThan(strpos($sh, 'php artisan up'), $a, 'Sinon le site est en maintenance (503) pendant le test.');
        $block = substr($sh, $a, strpos($sh, '# 6) Smoke test public') - $a);
        $this->assertDoesNotMatchRegularExpression('/\brm -rf\b|sessions\/\*|truncate|\.env\s*$/m', $block);
    }
}
