<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEPLOY — les images téléversées doivent être atteignables par le web
|--------------------------------------------------------------------------
| Constaté en production (diagnostic de déploiement) : le disque « public » de l'application hôte écrit
| dans <app>/public/uploads (URL /uploads), mais le serveur web sert public_html/. Les photos étaient
| enregistrées et introuvables (404). Le déploiement relie maintenant, dans public_html/uploads/tagtoa/,
| chaque sous-dossier d'images de TAGTOA — liste blanche : jamais tagtoa/pay-proofs (justificatifs).
|
| Le vrai bloc du script est exécuté ici, dans une arborescence temporaire, avec un faux `php artisan`.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;
use Symfony\Component\Process\Process;

class DeployUploadsLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $t;

    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY !== 'Linux' || trim((string) shell_exec('command -v bash')) === '') {
            $this->markTestSkipped('bash/Linux requis');
        }
        $this->t = sys_get_temp_dir().'/tg-up-'.bin2hex(random_bytes(4));
        mkdir($this->t.'/dom/laravel/public/uploads/tagtoa/pay-proofs', 0777, true);   // les justificatifs sont ICI, sous le disque public
        file_put_contents($this->t.'/dom/laravel/public/uploads/tagtoa/pay-proofs/secret.png', 'x');
        mkdir($this->t.'/dom/public_html/uploads', 0777, true);                         // dossier RÉEL, distinct (cas de la production)
        file_put_contents($this->t.'/dom/laravel/.env', "APP_URL=https://tagtoa.test\n");
        mkdir($this->t.'/bin', 0777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->t) && is_dir($this->t)) {
            (new Process(['rm', '-rf', $this->t]))->run();
        }
        parent::tearDown();
    }

    private function run4_76(string $diskLine): array
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $a = strpos($sh, '# 4.76) LES IMAGES');
        $b = strpos($sh, '# 4.8) Perf HTTP');
        $this->assertNotFalse($a);
        file_put_contents($this->t.'/block.sh', substr($sh, $a, $b - $a));
        // Faux « php artisan tinker » : renvoie la configuration du disque public.
        file_put_contents($this->t.'/bin/php', "#!/usr/bin/env bash\necho '".$diskLine."'\n");
        chmod($this->t.'/bin/php', 0755);

        $p = Process::fromShellCommandline('bash '.escapeshellarg($this->t.'/block.sh'), $this->t.'/dom/laravel', [
            'APP' => $this->t.'/dom/laravel', 'PH' => $this->t.'/dom/public_html',
            'PATH' => $this->t.'/bin:'.getenv('PATH'),
        ], null, 30);
        $p->run();

        return [$p->getExitCode(), $p->getOutput().$p->getErrorOutput()];
    }

    private function disk(): string
    {
        return $this->t.'/dom/laravel/public/uploads|https://tagtoa.test/uploads';
    }

    public function test_each_whitelisted_image_folder_is_linked_into_the_web_root_and_serves_the_real_files(): void
    {
        file_put_contents($this->t.'/dom/laravel/public/uploads/tagtoa/menu-items.x', '');   // bruit
        @mkdir($this->t.'/dom/laravel/public/uploads/tagtoa/menu-items', 0777, true);
        file_put_contents($this->t.'/dom/laravel/public/uploads/tagtoa/menu-items/photo.jpg', 'JPEG');

        [$code, $out] = $this->run4_76($this->disk());

        $this->assertSame(0, $code, $out);
        $link = $this->t.'/dom/public_html/uploads/tagtoa/menu-items';
        $this->assertTrue(is_link($link), $out);
        $this->assertSame('JPEG', file_get_contents($link.'/photo.jpg'), 'Le fichier téléversé est lisible par le chemin public.');
        foreach (['menu-covers', 'menu-logos', 'pos-products', 'event-covers', 'business-logos', 'pay-qr', 'site-gallery'] as $d) {
            $this->assertTrue(is_link($this->t.'/dom/public_html/uploads/tagtoa/'.$d), "tagtoa/$d doit être relié");
        }
    }

    public function test_payment_proofs_are_never_exposed(): void
    {
        $this->run4_76($this->disk());

        $this->assertFalse(file_exists($this->t.'/dom/public_html/uploads/tagtoa/pay-proofs'), 'Les justificatifs de paiement ne deviennent jamais publics.');
        $this->assertFalse(file_exists($this->t.'/dom/public_html/uploads/tagtoa/pay-proofs/secret.png'));
        $this->assertTrue(is_dir($this->t.'/dom/public_html/uploads/tagtoa'));
    }

    public function test_running_it_twice_changes_nothing_and_a_dead_link_is_repaired_a_real_folder_is_left_alone(): void
    {
        $this->run4_76($this->disk());
        $first = glob($this->t.'/dom/public_html/uploads/tagtoa/*');

        // Un vrai dossier déjà présent (ex. fichiers copiés à la main) : on n'y touche pas.
        (new Process(['rm', '-f', $this->t.'/dom/public_html/uploads/tagtoa/menu-logos']))->run();
        mkdir($this->t.'/dom/public_html/uploads/tagtoa/menu-logos');
        file_put_contents($this->t.'/dom/public_html/uploads/tagtoa/menu-logos/a.png', 'KEEP');
        // Un lien mort : refait.
        (new Process(['rm', '-f', $this->t.'/dom/public_html/uploads/tagtoa/menu-covers']))->run();
        symlink($this->t.'/nulle-part', $this->t.'/dom/public_html/uploads/tagtoa/menu-covers');

        [$code] = $this->run4_76($this->disk());

        $this->assertSame(0, $code);
        $this->assertSame('KEEP', file_get_contents($this->t.'/dom/public_html/uploads/tagtoa/menu-logos/a.png'), 'Un vrai dossier existant n\'est jamais écrasé.');
        $this->assertFalse(is_link($this->t.'/dom/public_html/uploads/tagtoa/menu-logos'));
        $this->assertTrue(is_link($this->t.'/dom/public_html/uploads/tagtoa/menu-covers') && file_exists($this->t.'/dom/public_html/uploads/tagtoa/menu-covers'), 'Le lien mort est refait.');
        $this->assertCount(count($first), glob($this->t.'/dom/public_html/uploads/tagtoa/*'));
    }

    public function test_nothing_is_done_when_the_disk_is_already_served_by_the_web_root(): void
    {
        // Disque public = public_html/uploads lui-même : rien à relier.
        [$code, $out] = $this->run4_76($this->t.'/dom/public_html/uploads|https://tagtoa.test/uploads');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('rien à relier', $out);
        $this->assertSame([], glob($this->t.'/dom/public_html/uploads/tagtoa/*') ?: []);
    }

    public function test_an_unknown_disk_configuration_never_breaks_the_deploy(): void
    {
        [$code, $out] = $this->run4_76('');

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('rien à relier', $out);
    }

    public function test_the_whitelist_covers_every_public_folder_the_code_writes_to(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        preg_match('/TAGTOA_PUBLIC_DIRS="([^"]+)"/', $sh, $m);
        $listed = explode(' ', $m[1]);

        $dirs = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            if (preg_match_all("/->store\\(\\s*'tagtoa\\/([a-z-]+)'\\s*,\\s*'public'\\s*\\)/", file_get_contents($f->getPathname()), $mm)) {
                $dirs = array_merge($dirs, $mm[1]);
            }
        }
        $missing = array_values(array_diff(array_unique($dirs), $listed));
        $this->assertSame([], $missing, 'Un dossier d\'images écrit par le code mais absent de la liste blanche resterait invisible.');
        $this->assertNotContains('pay-proofs', $listed);
    }
}
