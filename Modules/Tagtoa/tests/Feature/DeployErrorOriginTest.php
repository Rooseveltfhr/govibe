<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DÉPLOIEMENT — « origine des erreurs » dans le journal de déploiement
|--------------------------------------------------------------------------
| Le journal de déploiement ne donnait que le TITRE des dernières erreurs (« View [show] not found »), jamais QUEL fichier de
| l'application les provoque : la pile se trouve sur le serveur, hors de portée. Cette étape ajoute, pour chacune des 5 dernières
| erreurs, le premier cadre de pile hors vendor/ — fichier(ligne) seulement, JAMAIS d'arguments. Lecture seule, jamais bloquante.
| Le test exécute le VRAI extrait du script contre un faux journal.
*/

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DeployErrorOriginTest extends TestCase
{
    private function extrait(): string
    {
        $sh = file_get_contents(dirname(__DIR__, 2).'/deploy/remote-deploy.sh');
        $this->assertSame(1, preg_match('/# BEGIN origine-erreurs\n(.*?)# END origine-erreurs/s', $sh, $m), 'Marqueurs du bloc introuvables.');

        return $m[1];
    }

    /** Exécute l'extrait dans un dossier temporaire dont le journal contient $journal (null = pas de fichier). */
    private function lancer(?string $journal): array
    {
        $dir = sys_get_temp_dir().'/tagtoa-deploy-'.uniqid();
        mkdir($dir.'/storage/logs', 0777, true);
        if ($journal !== null) {
            file_put_contents($dir.'/storage/logs/laravel.log', $journal);
        }
        $p = new Process(['bash', '-c', $this->extrait()], $dir);
        $p->run();
        @unlink($dir.'/storage/logs/laravel.log');

        return [$p->getExitCode(), $p->getOutput()];
    }

    private function erreur(string $ts, string $titre, array $pile): string
    {
        return "[$ts] production.ERROR: $titre {\"exception\":\"[object] (X(code: 0): $titre at /srv/vendor/a.php:1)\n[stacktrace]\n".implode("\n", $pile)."\n";
    }

    public function test_it_names_the_first_application_frame_and_skips_vendor_and_internal_frames(): void
    {
        $log = $this->erreur('2026-11-03 07:48:33', 'View [show] not found.', [
            "#0 /srv/vendor/laravel/framework/src/Illuminate/View/FileViewFinder.php(79): Illuminate\\View\\FileViewFinder->findInPaths('show', Array)",
            "#1 /srv/vendor/laravel/framework/src/Illuminate/View/Factory.php(139): Illuminate\\View\\FileViewFinder->find('show')",
            "#2 /srv/laravel/app/Http/Controllers/Foo/BarController.php(52): Illuminate\\View\\Factory->make('show', Array)",
            "#3 [internal function]: App\\Http\\Controllers\\Foo\\BarController->show('secret-token-123')",
        ]);

        [$code, $out] = $this->lancer($log);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('View [show] not found.', $out);
        $this->assertStringContainsString('<- #2 /srv/laravel/app/Http/Controllers/Foo/BarController.php(52)', $out);
        $this->assertStringNotContainsString('/vendor/', explode('<-', $out)[1] ?? '', 'Jamais un cadre vendor/.');
    }

    public function test_it_never_prints_arguments_or_request_data(): void
    {
        $log = $this->erreur('2026-11-03 07:48:33', 'Boom', [
            "#0 /srv/laravel/app/Services/Pay/Thing.php(10): App\\Services\\Pay\\Thing->go('secret-token-123', Array)",
        ]);

        [, $out] = $this->lancer($log);

        $this->assertStringContainsString('Thing.php(10)', $out);
        $this->assertStringNotContainsString('secret-token-123', $out);
        $this->assertStringNotContainsString('Array', $out);
    }

    public function test_an_error_whose_stack_is_all_vendor_says_so_instead_of_inventing_a_frame(): void
    {
        $log = $this->erreur('2026-11-03 07:48:33', 'Vendor only', ['#0 /srv/vendor/x/y.php(1): Z->w()']);

        [, $out] = $this->lancer($log);

        $this->assertStringContainsString('(aucun cadre hors vendor/)', $out);
    }

    public function test_only_the_last_five_errors_are_shown_oldest_first(): void
    {
        $log = '';
        for ($i = 1; $i <= 8; $i++) {
            $log .= $this->erreur('2026-11-03 07:0'.$i.':00', 'Erreur numero '.$i, ['#0 /srv/laravel/app/F'.$i.'.php('.$i.'): A->b()']);
        }

        [, $out] = $this->lancer($log);

        $this->assertStringNotContainsString('numero 3', $out);
        foreach ([4, 5, 6, 7, 8] as $i) {
            $this->assertStringContainsString("Erreur numero $i", $out);
            $this->assertStringContainsString("F$i.php($i)", $out);
        }
        $this->assertLessThan(strpos($out, 'numero 8'), strpos($out, 'numero 4'), 'Du plus ancien au plus récent.');
    }

    public function test_with_several_application_frames_it_names_the_first_one_not_the_last(): void
    {
        $log = $this->erreur('2026-11-03 07:48:33', 'Deux cadres', [
            '#0 /srv/vendor/x/y.php(1): Z->w()',
            '#1 /srv/laravel/app/Http/Middleware/Authenticate.php(21): A->b()',
            '#2 /srv/laravel/app/Http/Kernel.php(99): C->d()',
            '#3 /srv/laravel/public/index.php(7): E->f()',
        ]);

        [, $out] = $this->lancer($log);

        $this->assertStringContainsString('Authenticate.php(21)', $out, 'Le premier cadre de l\'application : celui qui a appelé le code fautif.');
        $this->assertStringNotContainsString('Kernel.php', $out);
        $this->assertStringNotContainsString('index.php', $out);
    }

    public function test_only_the_end_of_a_huge_log_is_scanned(): void
    {
        $vieille = $this->erreur('2026-10-01 01:00:00', 'Vieille erreur oubliee', ['#0 /srv/laravel/app/Old.php(1): A->b()']);
        $remplissage = str_repeat("[2026-11-03 07:00:00] production.INFO: ligne de remplissage {}\n", 4500);
        $recente = $this->erreur('2026-11-03 09:00:00', 'Erreur recente', ['#0 /srv/laravel/app/New.php(2): A->b()']);

        [$code, $out] = $this->lancer($vieille.$remplissage.$recente);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Erreur recente', $out);
        $this->assertStringNotContainsString('Vieille erreur oubliee', $out, 'Un journal énorme ne se relit pas en entier à chaque déploiement.');
    }

    public function test_a_log_with_no_errors_or_no_log_file_never_fails_the_deploy(): void
    {
        [$code, $out] = $this->lancer("[2026-11-03 07:00:00] production.INFO: rien a signaler {}\n");
        $this->assertSame(0, $code);
        $this->assertStringContainsString('origine des erreurs', $out);

        [$code2] = $this->lancer(null);
        $this->assertSame(0, $code2, 'Pas de fichier journal : le déploiement continue.');
    }

    public function test_the_block_is_wired_into_the_deploy_script_and_the_script_still_parses(): void
    {
        $sh = file_get_contents(dirname(__DIR__, 2).'/deploy/remote-deploy.sh');

        $this->assertStringContainsString("|| true\n# END origine-erreurs", $sh, 'Le bloc ne doit jamais faire échouer le déploiement.');
        $this->assertLessThan(strpos($sh, '# BEGIN origine-erreurs'), strpos($sh, 'dernières erreurs (titres)'), 'Après la liste des titres.');
        $p = new Process(['bash', '-n', dirname(__DIR__, 2).'/deploy/remote-deploy.sh']);
        $p->run();
        $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput());
    }
}
