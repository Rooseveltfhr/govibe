<?php

namespace Modules\Tagtoa\App\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * php artisan tagtoa:backup-uploads [--src=] [--out=] [--keep=5] [--keep-daily=7] [--keep-weekly=4] [--max-mb=2048] [--timeout=300]
 *
 * BK-1c — sauvegarde des images téléversées (photos du menu, QR, logos, justificatifs de paiement), que la sauvegarde de la
 * base ne contient pas. Même discipline que tagtoa:backup-db :
 *  - écrit en `.partial`, relu jusqu'au bout (tar lisible, au moins un fichier), puis seulement renommé ;
 *  - 0600 dans un dossier 0700 (les justificatifs de paiement et les QR n'ont rien de public) ;
 *  - ne suit JAMAIS un lien symbolique (tar stocke le lien, pas ce qu'il vise) : rien d'extérieur au dossier n'est copié ;
 *  - rétention par niveaux, uniquement sur les fichiers que cette commande a faits ;
 *  - rien à refaire si rien n'a changé depuis la dernière archive (fichier ajouté, modifié OU supprimé) ;
 *  - garde-fous : taille totale plafonnée (--max-mb) et place disque suffisante, sinon échec CLAIR et aucune archive.
 *
 * Code de sortie : 0 = archive faite OU inutile ; 1 = échec.
 */
class BackupUploadsCommand extends Command
{
    public const PREFIX = 'tagtoa-uploads-';
    public const SUFFIX = '.tar.gz';
    private const NAME_RE = '/^tagtoa-uploads-\d{8}-\d{6}\.tar\.gz$/';

    protected $signature = 'tagtoa:backup-uploads
                            {--src= : Dossier à sauvegarder (défaut : <disque public>/tagtoa)}
                            {--out= : Dossier de destination (défaut : storage/app/backups)}
                            {--keep=5 : Archives les plus récentes toujours conservées}
                            {--keep-daily=7 : En plus : la plus récente de chaque jour, pendant ce nombre de jours (0 = aucun)}
                            {--keep-weekly=4 : En plus : la plus récente de chaque semaine, pendant ce nombre de semaines (0 = aucun)}
                            {--max-mb=2048 : Taille totale maximale des images à archiver, en Mo}
                            {--timeout=300 : Durée maximale de l\'archivage, en secondes}';

    protected $description = 'Sauvegarde (tar.gz vérifié) des images téléversées, avec rétention par niveaux';

    public function handle(): int
    {
        if (! ctype_digit((string) $this->option('keep')) || (int) $this->option('keep') < 1) {
            $this->error('--keep doit être un entier >= 1.');

            return self::FAILURE;
        }
        foreach (['keep-daily', 'keep-weekly'] as $opt) {
            if (! ctype_digit((string) $this->option($opt))) {
                $this->error("--$opt doit être un entier >= 0.");

                return self::FAILURE;
            }
        }
        foreach (['max-mb', 'timeout'] as $opt) {
            if (! ctype_digit((string) $this->option($opt)) || (int) $this->option($opt) < 1) {
                $this->error("--$opt doit être un entier >= 1.");

                return self::FAILURE;
            }
        }

        $src = rtrim((string) ($this->option('src') ?: rtrim((string) config('filesystems.disks.public.root'), '/').'/tagtoa'), '/');
        if ($src === '' || ! is_dir($src)) {
            $this->info('Aucun dossier d\'images à sauvegarder ('.($src ?: 'inconnu').').');

            return self::SUCCESS;
        }

        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/backups')), '/');
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Dossier de sauvegarde impossible à créer : $dir");

            return self::FAILURE;
        }
        @chmod($dir, 0700);

        // Le dossier de destination ne doit jamais être archivé dans lui-même (boucle qui grossit à chaque passage).
        if (str_starts_with(rtrim(realpath($dir) ?: $dir, '/').'/', rtrim(realpath($src) ?: $src, '/').'/')) {
            $this->error('Le dossier de destination est à l\'intérieur du dossier à sauvegarder.');

            return self::FAILURE;
        }

        [$count, $bytes, $newest] = $this->scan($src);
        if ($count === 0) {
            $this->info('Aucune image à sauvegarder.');

            return self::SUCCESS;
        }

        $files = self::listBackups($dir);
        if ($files !== [] && $newest < self::stampOf($files[0])->getTimestamp()) {
            $this->info('Rien n\'a changé depuis '.basename($files[0]).' — archive inutile ('.$count.' fichiers).');

            return self::SUCCESS;
        }

        $maxBytes = (int) $this->option('max-mb') * 1048576;
        if ($bytes > $maxBytes) {
            $this->error('Images trop volumineuses : '.number_format($bytes / 1048576, 0).' Mo > --max-mb='.(int) $this->option('max-mb').'. Aucune archive créée (augmentez --max-mb après avoir vérifié la place).');

            return self::FAILURE;
        }
        $libre = $this->freeBytes($dir);
        if ($libre !== false && $libre < $bytes * 1.2) {
            $this->error('Place disque insuffisante : '.number_format($libre / 1048576, 0).' Mo libres pour '.number_format($bytes / 1048576, 0).' Mo d\'images. Aucune archive créée.');

            return self::FAILURE;
        }

        $final = $dir.'/'.self::PREFIX.Carbon::now()->format('Ymd-His').self::SUFFIX;
        $part  = $final.'.partial';
        @unlink($part);
        $timeout = (int) $this->option('timeout');
        $this->cleanOrphanPartials($dir, $timeout + 300);

        $error = $this->archive($src, $part, $timeout);
        if ($error === null) {
            $error = self::verifyFile($part);
        }
        if ($error !== null) {
            @unlink($part);
            $this->error('Sauvegarde des images ÉCHOUÉE : '.$error);

            return self::FAILURE;
        }

        if (! @rename($part, $final)) {
            @unlink($part);
            $this->error('Sauvegarde des images ÉCHOUÉE : renommage final impossible.');

            return self::FAILURE;
        }

        $this->info('Sauvegarde des images OK : '.$final.' ('.number_format((int) filesize($final)).' octets, '.$count.' fichiers)');
        $this->prune($dir, $final);

        return self::SUCCESS;
    }

    /**
     * Compte les fichiers, leur taille totale et la date de la plus récente modification (fichiers ET dossiers :
     * supprimer un fichier modifie le dossier qui le contenait). Les liens symboliques ne sont jamais suivis.
     *
     * @return array{0:int,1:int,2:int}
     */
    protected function scan(string $src): array
    {
        $count = 0;
        $bytes = 0;
        $newest = (int) @filemtime($src);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),   // pas FOLLOW_SYMLINKS
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $info) {
            /** @var \SplFileInfo $info */
            $newest = max($newest, (int) $info->getMTime());
            if ($info->isLink()) {
                continue;
            }
            if ($info->isFile()) {
                $count++;
                $bytes += (int) $info->getSize();
            }
        }

        return [$count, $bytes, $newest];
    }

    /** Place libre (point d'injection des tests). */
    protected function freeBytes(string $dir): float|false
    {
        return @disk_free_space($dir);
    }

    /** Code 1 = « un fichier a changé pendant la lecture » (un client téléverse pendant l'archivage) : l'archive reste utilisable. */
    public static function tarExitIsUsable(?int $code): bool
    {
        return $code === 0 || $code === 1;
    }

    /** Lance tar. Retourne l'erreur, ou null. */
    protected function archive(string $src, string $target, int $timeout): ?string
    {
        // -C parent + nom du dossier : l'archive contient « tagtoa/... » (restauration : tar -xzf dans le dossier du disque public).
        $p = new Process(['tar', '-czf', $target, '-C', dirname($src), basename($src)], null, null, null, $timeout);
        try {
            $p->run();
        } catch (\Throwable $e) {
            return 'tar impossible : '.substr($e->getMessage(), 0, 300);
        }
        @chmod($target, 0600);
        if (! self::tarExitIsUsable($p->getExitCode())) {
            return 'tar a retourné '.$p->getExitCode().' — '.trim(substr($p->getErrorOutput(), 0, 400));
        }

        return null;
    }

    /** Archive lisible jusqu'au bout et non vide. Utilisable hors de la commande (tagtoa:backup-verify). */
    public static function verifyFile(string $file): ?string
    {
        if (! is_file($file)) {
            return 'archive introuvable';
        }
        $p = new Process(['tar', '-tzf', $file], null, null, null, 120);
        try {
            $p->run();
        } catch (\Throwable $e) {
            return 'vérification impossible : '.substr($e->getMessage(), 0, 300);
        }
        if ($p->getExitCode() !== 0) {
            return 'archive corrompue ou tronquée ('.trim(substr($p->getErrorOutput(), 0, 300)).')';
        }
        if (trim($p->getOutput()) === '') {
            return 'archive sans aucun fichier';
        }

        return null;
    }

    /** Les archives de CETTE commande, de la plus récente à la plus ancienne. */
    public static function listBackups(string $dir): array
    {
        $files = [];
        foreach (@scandir($dir) ?: [] as $name) {
            if (preg_match(self::NAME_RE, $name) === 1) {
                $files[] = $dir.'/'.$name;
            }
        }
        rsort($files);

        return $files;
    }

    public static function stampOf(string $file): Carbon
    {
        preg_match('/(\d{8})-(\d{6})/', basename($file), $m);

        return Carbon::createFromFormat('Ymd-His', $m[1].'-'.$m[2]);
    }

    protected function cleanOrphanPartials(string $dir, int $olderThanSeconds): void
    {
        foreach (@scandir($dir) ?: [] as $name) {
            if (preg_match('/^tagtoa-uploads-\d{8}-\d{6}\.tar\.gz\.partial$/', $name) !== 1) {
                continue;
            }
            $f = $dir.'/'.$name;
            if (is_file($f) && time() - (int) @filemtime($f) > $olderThanSeconds && @unlink($f)) {
                $this->line('Archive partielle orpheline supprimée : '.$name);
            }
        }
    }

    protected function prune(string $dir, string $justMade): array
    {
        $files = self::listBackups($dir);
        $garde = BackupRetention::keepSet($files, fn ($f) => self::stampOf($f),
            (int) $this->option('keep'), (int) $this->option('keep-daily'), (int) $this->option('keep-weekly'));
        foreach ($files as $f) {
            if (! isset($garde[$f]) && $f !== $justMade && @unlink($f)) {
                $this->line('Ancienne archive supprimée : '.basename($f));
            }
        }

        return array_map('basename', array_keys($garde));
    }
}
