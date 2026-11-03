<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA SAUVEGARDE (BK-1c) — tagtoa:backup-uploads : les images téléversées
|--------------------------------------------------------------------------
| Photos du menu, QR, logos, justificatifs de paiement : absents du dump de la base. Ici on archive pour de vrai (vrai tar)
| et on relit l'archive : contenu exact, permissions, liens symboliques jamais suivis, rétention, « rien n'a changé ».
*/

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Console\BackupUploadsCommand;
use Modules\Tagtoa\Tests\TestCase;
use Symfony\Component\Process\Process;

class BackupUploadsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $root;
    private string $src;
    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/tagtoa-bku-'.bin2hex(random_bytes(4));
        $this->src = $this->root.'/disk/tagtoa';
        $this->out = $this->root.'/backups';
        mkdir($this->src.'/menu-items', 0755, true);
        mkdir($this->src.'/pay-proofs', 0755, true);
        file_put_contents($this->src.'/menu-items/a.jpg', 'photo-a');
        file_put_contents($this->src.'/pay-proofs/p.png', 'preuve-p');
        $this->ageAll(time() - 1000);   // « rien n'a changé » se juge sur les dates : tout est ancien au départ
    }

    /** Date de modification de tout le contenu (dossiers compris). */
    private function ageAll(int $ts): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->src, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $i) { @touch($i->getPathname(), $ts); }
        touch($this->src, $ts);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();
        parent::tearDown();
    }

    private function run_(array $opts = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('tagtoa:backup-uploads', array_merge(['--src' => $this->src, '--out' => $this->out], $opts));
    }

    private function archives(): array
    {
        return BackupUploadsCommand::listBackups($this->out);
    }

    private function listing(string $file): array
    {
        $p = new Process(['tar', '-tzf', $file]);
        $p->run();

        return array_values(array_filter(explode("\n", trim($p->getOutput()))));
    }

    /** Pose une archive factice datée (pour la rétention). */
    private function fake(Carbon $when): string
    {
        @mkdir($this->out, 0700, true);
        $f = $this->out.'/tagtoa-uploads-'.$when->format('Ymd-His').'.tar.gz';
        $p = new Process(['tar', '-czf', $f, '-C', dirname($this->src), 'tagtoa']);
        $p->run();

        return $f;
    }

    public function test_it_archives_every_image_including_payment_proofs_with_private_permissions(): void
    {
        $this->run_()->assertExitCode(0);

        $a = $this->archives();
        $this->assertCount(1, $a);
        $list = $this->listing($a[0]);
        $this->assertContains('tagtoa/menu-items/a.jpg', $list);
        $this->assertContains('tagtoa/pay-proofs/p.png', $list, 'Les justificatifs de paiement font partie de ce qu\'on perdrait.');
        $this->assertSame('0600', substr(sprintf('%o', fileperms($a[0])), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->out)), -4));
        $this->assertSame([], glob($this->out.'/*.partial') ?: []);
    }

    public function test_the_archive_restores_the_exact_content(): void
    {
        $this->run_()->assertExitCode(0);
        $dest = $this->root.'/restore';
        mkdir($dest);
        (new Process(['tar', '-xzf', $this->archives()[0], '-C', $dest]))->mustRun();

        $this->assertSame('photo-a', file_get_contents($dest.'/tagtoa/menu-items/a.jpg'));
        $this->assertSame('preuve-p', file_get_contents($dest.'/tagtoa/pay-proofs/p.png'));
    }

    public function test_a_symlink_is_never_followed_so_nothing_outside_the_folder_is_copied(): void
    {
        file_put_contents($this->root.'/secret.txt', 'HORS-DOSSIER');
        symlink($this->root.'/secret.txt', $this->src.'/menu-items/lien.txt');
        symlink($this->root.'/disk', $this->src.'/boucle');   // pointe vers le parent : suivre = boucle infinie

        $this->run_()->assertExitCode(0);

        $dest = $this->root.'/restore';
        mkdir($dest);
        (new Process(['tar', '-xzf', $this->archives()[0], '-C', $dest]))->mustRun();
        $this->assertTrue(is_link($dest.'/tagtoa/menu-items/lien.txt'), 'Le lien est stocké comme lien…');
        $this->assertStringNotContainsString('HORS-DOSSIER', (new Process(['tar', '-xzOf', $this->archives()[0]]))->mustRun()->getOutput(), '…jamais le contenu de ce qu\'il vise.');
    }

    public function test_nothing_changed_means_no_second_archive_but_any_change_triggers_one(): void
    {
        $this->run_()->assertExitCode(0);
        $this->assertCount(1, $this->archives());

        $this->run_()->expectsOutputToContain('Rien n\'a changé')->assertExitCode(0);
        $this->assertCount(1, $this->archives());

        // Un fichier ajouté APRÈS la dernière archive : nouvelle archive.
        file_put_contents($this->src.'/menu-items/b.jpg', 'photo-b');
        touch($this->src.'/menu-items/b.jpg', time() + 5);
        $this->travelTo(Carbon::now()->addSeconds(10));
        $this->run_()->assertExitCode(0);
        $this->assertCount(2, $this->archives());
    }

    public function test_a_deleted_file_counts_as_a_change(): void
    {
        $this->run_()->assertExitCode(0);
        unlink($this->src.'/menu-items/a.jpg');
        touch($this->src.'/menu-items', time() + 5);   // supprimer un fichier modifie le dossier qui le contenait
        $this->travelTo(Carbon::now()->addSeconds(10));

        $this->run_()->assertExitCode(0);
        $this->assertCount(2, $this->archives());
    }

    public function test_a_missing_or_empty_source_is_a_clean_no_op(): void
    {
        $this->run_(['--src' => $this->root.'/inexistant'])->expectsOutputToContain('Aucun dossier')->assertExitCode(0);

        $vide = $this->root.'/vide';
        mkdir($vide);
        $this->run_(['--src' => $vide])->expectsOutputToContain('Aucune image')->assertExitCode(0);
        $this->assertSame([], $this->archives());
    }

    public function test_too_many_megabytes_fails_loudly_and_creates_nothing(): void
    {
        file_put_contents($this->src.'/gros.bin', str_repeat('x', 3 * 1048576));

        $this->run_(['--max-mb' => 1])->expectsOutputToContain('trop volumineuses')->assertExitCode(1);

        $this->assertSame([], $this->archives());
        $this->assertSame([], glob($this->out.'/*.partial') ?: []);
    }

    public function test_an_output_folder_inside_the_source_is_refused(): void
    {
        $this->run_(['--out' => $this->src.'/backups'])->expectsOutputToContain('à l\'intérieur')->assertExitCode(1);
    }

    public function test_invalid_options_are_refused(): void
    {
        foreach (['--keep' => '0', '--keep-daily' => '7j', '--max-mb' => '0', '--timeout' => 'x'] as $opt => $val) {
            $this->run_([$opt => $val])->assertExitCode(1);
        }
        $this->assertSame([], $this->archives());
    }

    public function test_a_truncated_archive_is_detected_by_verify_file(): void
    {
        $this->run_()->assertExitCode(0);
        $f = $this->archives()[0];
        $this->assertNull(BackupUploadsCommand::verifyFile($f));

        $bin = file_get_contents($f);
        file_put_contents($f, substr($bin, 0, (int) (strlen($bin) / 2)));
        $this->assertNotNull(BackupUploadsCommand::verifyFile($f));

        file_put_contents($f, '');
        $this->assertNotNull(BackupUploadsCommand::verifyFile($f));
        $this->assertNotNull(BackupUploadsCommand::verifyFile($this->out.'/absent.tar.gz'));
    }

    public function test_tiered_retention_keeps_recent_daily_and_weekly_and_never_touches_foreign_files(): void
    {
        $now = Carbon::now();
        $this->travelTo($now->copy()->addDays(100));   // la nouvelle archive est « aujourd'hui », les factices sont très anciennes
        $vieille = $this->fake($now->copy()->subDays(1));            // ≈ 101 j : hors de tous les niveaux
        $recentes = [];
        for ($i = 0; $i < 3; $i++) {
            $recentes[] = $this->fake($now->copy()->addDays(99)->subMinutes($i + 1));
        }
        file_put_contents($this->out.'/tagtoa-uploads-manuel.tar.gz', 'à moi');
        file_put_contents($this->out.'/autre.tar.gz', 'autre');
        touch($this->src.'/menu-items/a.jpg', time() + 99 * 86400 + 3600);   // un changement plus récent que les archives factices

        $this->run_(['--keep' => 2, '--keep-daily' => 0, '--keep-weekly' => 0])->assertExitCode(0);

        $restantes = array_map('basename', $this->archives());
        $this->assertCount(2, $restantes, 'Seulement --keep archives, niveaux désactivés.');
        $this->assertFileDoesNotExist($vieille);
        $this->assertFileExists($this->out.'/tagtoa-uploads-manuel.tar.gz', 'Un fichier nommé à la main n\'est jamais supprimé.');
        $this->assertFileExists($this->out.'/autre.tar.gz');
    }

    public function test_orphan_partials_are_cleaned_but_recent_and_foreign_ones_are_not(): void
    {
        mkdir($this->out, 0700, true);
        $vieux = $this->out.'/tagtoa-uploads-20200101-000000.tar.gz.partial';
        $recent = $this->out.'/tagtoa-uploads-20200102-000000.tar.gz.partial';
        $autre = $this->out.'/mon.partial';
        foreach ([$vieux, $recent, $autre] as $f) { file_put_contents($f, 'x'); }
        touch($vieux, time() - 7200);
        touch($autre, time() - 7200);

        $this->run_(['--timeout' => 60])->assertExitCode(0);

        $this->assertFileDoesNotExist($vieux);
        $this->assertFileExists($recent);
        $this->assertFileExists($autre);
    }

    public function test_an_output_path_with_glob_characters_still_works(): void
    {
        $this->out .= '[x]';
        $this->run_()->assertExitCode(0);
        $this->assertCount(1, $this->archives());
    }

    public function test_the_deploy_script_runs_it_after_the_database_backup_and_honours_required(): void
    {
        $sh = file_get_contents(__DIR__.'/../../deploy/remote-deploy.sh');
        $db = strpos($sh, 'tagtoa:backup-db');
        $up = strpos($sh, 'tagtoa:backup-uploads');
        $this->assertNotFalse($up);
        $this->assertStringContainsString('php artisan tagtoa:backup-uploads --keep=', $sh);
        $this->assertGreaterThan($db, $up);
        $this->assertLessThan(strpos($sh, 'php artisan down'), $up, 'Avant la maintenance.');
        $this->assertLessThan(strpos($sh, 'tagtoa:backup-verify'), $up);
        $this->assertStringContainsString('TAGTOA_UPLOADS_BACKUP_SKIP', $sh);
        $this->assertStringContainsString('--max-mb="${TAGTOA_UPLOADS_MAX_MB:-2048}"', $sh);
        $this->assertMatchesRegularExpression('/SAUVEGARDE DES IMAGES IMPOSSIBLE.*?TAGTOA_BACKUP_REQUIRED/s', $sh);
    }

    public function test_a_change_in_the_very_second_of_the_last_archive_is_not_missed(): void
    {
        $this->run_()->assertExitCode(0);
        $stamp = BackupUploadsCommand::stampOf($this->archives()[0])->getTimestamp();
        touch($this->src.'/menu-items/a.jpg', $stamp);   // modifié dans la même seconde : peut-être après la lecture de tar
        $this->travelTo(Carbon::now()->addSeconds(10));

        $this->run_()->assertExitCode(0);
        $this->assertCount(2, $this->archives());
    }

    public function test_not_enough_free_disk_space_fails_and_creates_nothing(): void
    {
        $this->app->bind(BackupUploadsCommand::class, fn () => new class extends BackupUploadsCommand {
            protected function freeBytes(string $dir): float|false { return 1.0; }
        });
        $this->run_()->expectsOutputToContain('Place disque insuffisante')->assertExitCode(1);
        $this->assertSame([], $this->archives());
    }

    public function test_an_existing_output_folder_with_open_permissions_is_closed_to_0700(): void
    {
        mkdir($this->out, 0755, true);
        chmod($this->out, 0755);
        $this->run_()->assertExitCode(0);
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->out)), -4));
    }

    public function test_only_tar_exit_codes_0_and_1_leave_a_usable_archive(): void
    {
        $this->assertTrue(BackupUploadsCommand::tarExitIsUsable(0));
        $this->assertTrue(BackupUploadsCommand::tarExitIsUsable(1), 'Un fichier a changé pendant la lecture : l\'archive reste bonne.');
        $this->assertFalse(BackupUploadsCommand::tarExitIsUsable(2));
        $this->assertFalse(BackupUploadsCommand::tarExitIsUsable(127));
        $this->assertFalse(BackupUploadsCommand::tarExitIsUsable(null));
    }

    public function test_a_valid_but_empty_tar_is_not_a_backup(): void
    {
        @mkdir($this->out, 0700, true);
        $f = $this->out.'/vide.tar.gz';
        file_put_contents($f, gzencode(str_repeat("\0", 10240)));   // tar valide, sans aucune entrée
        $this->assertSame('archive sans aucun fichier', BackupUploadsCommand::verifyFile($f));
    }

    public function test_a_symlink_to_a_big_outside_file_does_not_count_toward_the_size_cap(): void
    {
        file_put_contents($this->root.'/gros.bin', str_repeat('x', 3 * 1048576));
        symlink($this->root.'/gros.bin', $this->src.'/menu-items/lien-gros.bin');

        $this->run_(['--max-mb' => 1])->assertExitCode(0);
    }

    public function test_the_archive_just_made_survives_even_when_a_future_dated_one_takes_the_only_slot(): void
    {
        $futur = $this->fake(Carbon::now()->addDay());
        touch($this->src.'/menu-items/a.jpg', time() + 2 * 86400);   // plus récent que l'archive factice : on archive

        $this->run_(['--keep' => 1, '--keep-daily' => 0, '--keep-weekly' => 0])->assertExitCode(0);

        $noms = array_map('basename', $this->archives());
        $this->assertCount(2, $noms, 'Celle qui vient d\'être faite n\'est jamais supprimée, même sans place.');
    }
}
