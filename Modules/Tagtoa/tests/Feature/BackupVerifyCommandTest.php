<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA SAUVEGARDE (BK-1a) — tagtoa:backup-verify : relire la dernière sauvegarde du serveur
|--------------------------------------------------------------------------
| Lecture seule. Une sauvegarde qui n'a jamais été relue est un espoir : ici on contrôle le fichier RÉEL — archive complète,
| CHAQUE table de la base présente dans le dump, taille cohérente avec la précédente, place prise. Ce n'est pas une
| restauration (celle-là est prouvée en CI sur MySQL 8) et la commande le dit.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Console\BackupDatabaseCommand;
use Modules\Tagtoa\Tests\TestCase;

class BackupVerifyCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/tagtoa-bkv-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        (new \Symfony\Component\Process\Process(['rm','-rf',$this->dir]))->run();
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function tablesVives(): array
    {
        return array_values(array_filter(Schema::getTableListing(), fn ($t) => ! str_starts_with((string) $t, 'sqlite_')));
    }

    /** Écrit une sauvegarde datée ; $sans = tables omises ; $complet = pied de page présent ; $padding = octets de remplissage. */
    private function sauvegarde(string $stamp, array $sans = [], bool $complet = true, int $padding = 0): string
    {
        $dump = "-- dump\n";
        foreach ($this->tablesVives() as $t) {
            if (! in_array($t, $sans, true)) {
                $dump .= "CREATE TABLE `{$t}` (id int);\nINSERT INTO `{$t}` VALUES (1);\n";
            }
        }
        $dump .= $padding > 1 ? bin2hex(random_bytes((int) ($padding / 2))) : '';   // incompressible : la taille du .gz suit vraiment le remplissage
        $dump .= $complet ? "\n-- Dump completed on 2026-11-03\n" : "\n";
        $f = $this->dir."/tagtoa-db-$stamp.sql.gz";
        file_put_contents($f, gzencode($dump));

        return $f;
    }

    public function test_a_complete_backup_with_every_table_is_reported_healthy(): void
    {
        $this->sauvegarde('20260101-010101', padding: 20000);
        $this->sauvegarde('20260102-010101', padding: 20000);

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('Dernière sauvegarde : tagtoa-db-20260102-010101.sql.gz')
            ->expectsOutputToContain('dump complet : oui')
            ->expectsOutputToContain('tables de la base présentes dans le dump : '.count($this->tablesVives()))
            ->expectsOutputToContain('taille cohérente avec la précédente : oui')
            ->expectsOutputToContain('Sauvegardes conservées : 2')
            ->expectsOutputToContain('pas une restauration')
            ->assertExitCode(0);
    }

    public function test_a_backup_missing_a_table_fails_and_names_it(): void
    {
        $manquante = $this->tablesVives()[0];
        $this->sauvegarde('20260102-010101', sans: [$manquante]);

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('absente(s) du dump : '.$manquante)
            ->assertExitCode(1);
    }

    public function test_a_truncated_dump_without_its_footer_fails(): void
    {
        $this->sauvegarde('20260102-010101', complet: false);

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('dump incomplet')
            ->assertExitCode(1);
    }

    public function test_an_unreadable_archive_fails(): void
    {
        file_put_contents($this->dir.'/tagtoa-db-20260102-010101.sql.gz', 'pas du gzip du tout');

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])->assertExitCode(1);
    }

    public function test_no_backup_at_all_fails_loudly(): void
    {
        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('Aucune sauvegarde trouvée')
            ->assertExitCode(1);
    }

    public function test_a_sudden_size_drop_against_the_previous_backup_fails(): void
    {
        $this->sauvegarde('20260101-010101', padding: 200000);
        $this->sauvegarde('20260102-010101', padding: 0);

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('moins de 50 %')
            ->assertExitCode(1);
    }

    public function test_a_hand_named_file_is_never_taken_for_the_latest_backup(): void
    {
        $this->sauvegarde('20260102-010101');
        file_put_contents($this->dir.'/tagtoa-db-zzz-a-la-main.sql.gz', 'pas un dump');

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('tagtoa-db-20260102-010101.sql.gz')
            ->assertExitCode(0);
    }

    public function test_it_is_read_only_and_leaves_every_file_as_it_found_it(): void
    {
        $a = $this->sauvegarde('20260101-010101');
        $b = $this->sauvegarde('20260102-010101');
        $avant = [md5_file($a), md5_file($b), filemtime($a), filemtime($b)];

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])->run();

        $this->assertSame($avant, [md5_file($a), md5_file($b), filemtime($a), filemtime($b)]);
        $this->assertCount(2, BackupDatabaseCommand::listBackups($this->dir));
    }

    public function test_max_age_flags_a_stale_latest_backup_but_accepts_a_fresh_one(): void
    {
        $this->sauvegarde(now()->subDays(3)->format('Ymd-His'), padding: 20000);
        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir, '--max-age' => 30])
            ->expectsOutputToContain('aucune sauvegarde récente')
            ->assertExitCode(1);

        $this->sauvegarde(now()->subMinutes(2)->format('Ymd-His'), padding: 20000);
        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir, '--max-age' => 30])->assertExitCode(0);
    }

    public function test_without_max_age_an_old_backup_is_not_a_problem(): void
    {
        $this->sauvegarde('20200101-010101', padding: 20000);
        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])->assertExitCode(0);
    }

    public function test_the_latest_images_archive_is_checked_too_and_a_corrupt_one_fails(): void
    {
        $this->sauvegarde('20260102-010101', padding: 20000);
        $src = $this->dir.'/src/tagtoa';
        mkdir($src, 0755, true);
        file_put_contents($src.'/a.jpg', 'x');
        $tar = $this->dir.'/tagtoa-uploads-20260102-010102.tar.gz';
        (new \Symfony\Component\Process\Process(['tar', '-czf', $tar, '-C', dirname($src), 'tagtoa']))->mustRun();

        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('images : tagtoa-uploads-20260102-010102.tar.gz lisible jusqu\'au bout')
            ->assertExitCode(0);

        file_put_contents($tar, substr((string) file_get_contents($tar), 0, 20));
        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])
            ->expectsOutputToContain('PROBLÈME (images)')
            ->assertExitCode(1);
    }

    public function test_no_images_archive_is_not_a_problem(): void
    {
        $this->sauvegarde('20260102-010101', padding: 20000);
        $this->artisan('tagtoa:backup-verify', ['--dir' => $this->dir])->assertExitCode(0);
    }
}
