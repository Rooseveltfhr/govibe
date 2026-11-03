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
        // realpath : si le dossier est lui-même un lien (dossier « shared » d'un déploiement), tar n'archiverait QUE le lien
        // (une entrée) alors que le scan compterait les vrais fichiers → fausse bonne archive qui évince les vraies.
        $reel = $src === '' ? false : realpath($src);
        if ($reel === false || ! is_dir($reel)) {
            $this->info('Aucun dossier d\'images à sauvegarder ('.($src ?: 'inconnu').').');

            return self::SUCCESS;
        }
        $src = $reel;

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

        // Deux déploiements simultanés partageraient le même fichier partiel : l'un effacerait celui de l'autre.
        $lock = @fopen($dir.'/.tagtoa-uploads.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->info('Une sauvegarde des images est déjà en cours — celle-ci est ignorée.');

            return self::SUCCESS;
        }

        try {
            return $this->sauvegarder($src, $dir);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function sauvegarder(string $src, string $dir): int
    {
        [$count, $bytes, $signature] = $this->scan($src);
        if ($count === 0) {
            $this->info('Aucune image à sauvegarder.');

            return self::SUCCESS;
        }

        // « Rien n'a changé » = même liste de (chemin, taille, date) que lors de la dernière archive RÉUSSIE, et cette archive existe
        // encore. Une comparaison de dates avec le nom de l'archive ratait les fichiers restaurés avec leur vieille date (rsync -a, tar -x).
        $etat = $this->readState($dir);
        $files = self::listBackups($dir);
        if ($etat !== null && $etat['sig'] === $signature && in_array($dir.'/'.$etat['archive'], $files, true)) {
            $this->info('Rien n\'a changé depuis '.$etat['archive'].' — archive inutile ('.$count.' fichiers).');

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

        $this->writeState($dir, $signature, basename($final));
        $this->info('Sauvegarde des images OK : '.$final.' ('.number_format((int) filesize($final)).' octets, '.$count.' fichiers)');
        $this->prune($dir, $final);

        return self::SUCCESS;
    }

    /**
     * Compte les fichiers et leur taille totale, et calcule la signature du contenu (chemin, taille, date de chaque fichier).
     * Les liens symboliques ne sont jamais suivis ni lus (lstat) : un lien cassé ou pointant dehors ne fait rien planter.
     *
     * @return array{0:int,1:int,2:string}
     */
    protected function scan(string $src): array
    {
        $count = 0;
        $bytes = 0;
        $lignes = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),   // pas FOLLOW_SYMLINKS
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $info) {
            /** @var \SplFileInfo $info */
            $st = @lstat($info->getPathname());   // lstat : ne suit pas le lien ; false si le fichier vient d'être supprimé
            if ($st === false) {
                continue;
            }
            $mode = $st['mode'] & 0170000;
            if ($mode === 0100000) {              // fichier ordinaire
                $count++;
                $bytes += (int) $st['size'];
                $lignes[] = substr($info->getPathname(), strlen($src)).'|'.$st['size'].'|'.$st['mtime'];
            } elseif ($mode === 0120000) {        // lien : compté dans la signature, jamais lu
                $lignes[] = substr($info->getPathname(), strlen($src)).'|link';
            }
        }
        sort($lignes);

        return [$count, $bytes, sha1(implode("\n", $lignes))];
    }

    protected function statePath(string $dir): string
    {
        return $dir.'/tagtoa-uploads.state';
    }

    /** @return array{sig:string,archive:string}|null */
    protected function readState(string $dir): ?array
    {
        $raw = @file_get_contents($this->statePath($dir));
        $d = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($d) && isset($d['sig'], $d['archive']) && is_string($d['sig']) && is_string($d['archive'])
            && preg_match(self::NAME_RE, $d['archive']) === 1 ? ['sig' => $d['sig'], 'archive' => $d['archive']] : null;
    }

    protected function writeState(string $dir, string $sig, string $archive): void
    {
        $f = $this->statePath($dir);
        @file_put_contents($f, json_encode(['sig' => $sig, 'archive' => $archive]));
        @chmod($f, 0600);
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
        $p = new Process(['tar', '--force-local', '-czf', $target, '-C', dirname($src), '--', basename($src)], null, null, null, $timeout);
        try {
            $p->run();
        } catch (\Throwable $e) {
            return 'tar impossible : '.substr($e->getMessage(), 0, 300);
        }
        @chmod($target, 0600);
        if (! self::tarExitIsUsable($p->getExitCode())) {
            return 'tar a retourné '.$p->getExitCode().' — '.trim(substr($p->getErrorOutput(), 0, 400));
        }
        if ($p->getExitCode() === 1) {
            $this->warn('Un fichier a changé pendant l\'archivage (téléversement en cours) : il peut être tronqué dans cette archive — '.trim(substr($p->getErrorOutput(), 0, 200)));
        }

        return null;
    }

    /** Archive lisible jusqu'au bout et non vide. Utilisable hors de la commande (tagtoa:backup-verify). */
    public static function verifyFile(string $file): ?string
    {
        if (! is_file($file)) {
            return 'archive introuvable';
        }
        $p = new Process(['tar', '-tvzf', $file], null, null, null, 120);
        try {
            $p->run();
        } catch (\Throwable $e) {
            return 'vérification impossible : '.substr($e->getMessage(), 0, 300);
        }
        if ($p->getExitCode() !== 0) {
            return 'archive corrompue ou tronquée ('.trim(substr($p->getErrorOutput(), 0, 300)).')';
        }
        // -tv : le premier caractère de chaque ligne est le type (« - » fichier ordinaire, « d » dossier, « l » lien).
        // Une archive faite de dossiers et de liens seulement n'est pas une sauvegarde d'images.
        if (preg_match('/^-/m', $p->getOutput()) !== 1) {
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
