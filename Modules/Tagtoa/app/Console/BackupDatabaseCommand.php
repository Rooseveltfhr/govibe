<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Process\Process;

/**
 * TAGTOA — sauvegarde de la base AVANT une migration.
 *
 *   php artisan tagtoa:backup-db --if-pending
 *
 * Pourquoi. `remote-deploy.sh` annule un déploiement raté en DÉSACTIVANT le
 * module : il ne défait aucune migration. Une migration qui échoue à mi-chemin
 * (ou qui réussit en altérant des données) laissait donc la base dans un état
 * que rien ne savait restaurer — et aucune sauvegarde n'existait avant elle.
 *
 * Ce que la commande garantit :
 *  - le mot de passe ne passe JAMAIS dans la ligne de commande (visible par
 *    `ps` pour tout utilisateur du serveur) mais par l'environnement du seul
 *    processus de dump ;
 *  - le fichier est écrit en `.partial`, vérifié (gzip lisible jusqu'au bout ET
 *    pied de page du dump présent), puis seulement renommé : un dump tronqué
 *    ne ressemble jamais à une sauvegarde ;
 *  - le fichier est 0600 dans un dossier 0700 : il contient TOUT, secrets des
 *    passerelles compris ;
 *  - la rétention ne supprime que les sauvegardes que cette commande a faites.
 *
 * Code de sortie : 0 = sauvegarde faite OU inutile (--if-pending sans
 * migration en attente) ; 1 = échec. Le script de déploiement décide s'il
 * bloque (TAGTOA_BACKUP_REQUIRED=1) ou s'il prévient bruyamment et continue.
 */
class BackupDatabaseCommand extends Command
{
    public const PREFIX = 'tagtoa-db-';
    public const SUFFIX = '.sql.gz';
    /** Dernière ligne que mysqldump/mariadb-dump écrit quand le dump est complet. */
    public const FOOTER = '-- Dump completed';

    protected $signature = 'tagtoa:backup-db
                            {--if-pending : Ne sauvegarder que si une migration est en attente}
                            {--keep=14 : Nombre de sauvegardes conservées (les plus récentes)}
                            {--out= : Dossier de destination (défaut : storage/app/backups)}
                            {--timeout=900 : Durée maximale du dump, en secondes}';

    protected $description = 'Sauvegarde la base (mysqldump compressé, vérifié) avant une migration';

    public function handle(): int
    {
        // « 14d » deviendrait 0 puis 1 : toutes les anciennes sauvegardes effacées.
        if (! ctype_digit((string) $this->option('keep')) || (int) $this->option('keep') < 1) {
            $this->error('--keep doit être un entier >= 1.');

            return self::FAILURE;
        }

        // Par son nom : « migrator » est le seul identifiant que le conteneur sait
        // résoudre (la classe Migrator, elle, n'est pas liée → injection en échec).
        if ($this->option('if-pending') && ! $this->hasPendingMigrations($this->laravel->make('migrator'))) {
            $this->info('Aucune migration en attente — sauvegarde inutile.');

            return self::SUCCESS;
        }

        $conn = (array) config('database.connections.'.config('database.default'));
        if (! in_array($conn['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            $this->error('Sauvegarde prise en charge pour MySQL/MariaDB uniquement (pilote : '.($conn['driver'] ?? '?').').');

            return self::FAILURE;
        }

        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/backups')), '/');
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Dossier de sauvegarde impossible à créer : $dir");

            return self::FAILURE;
        }
        @chmod($dir, 0700);

        $final = $dir.'/'.self::PREFIX.date('Ymd-His').self::SUFFIX;
        $part  = $final.'.partial';
        @unlink($part);

        $error = $this->dump($conn, $part, max(30, (int) $this->option('timeout')));
        if ($error === null) {
            $error = $this->verify($part);
        }
        if ($error !== null) {
            @unlink($part);
            $this->error('Sauvegarde ÉCHOUÉE : '.$error);

            return self::FAILURE;
        }

        @chmod($part, 0600);
        if (! @rename($part, $final)) {
            @unlink($part);
            $this->error('Sauvegarde ÉCHOUÉE : renommage final impossible.');

            return self::FAILURE;
        }

        $this->info('Sauvegarde OK : '.$final.' ('.number_format((int) filesize($final)).' octets)');
        $this->prune($dir, (int) $this->option('keep'), $final);

        return self::SUCCESS;
    }

    /** Une migration est-elle en attente (dossier de l'hôte + chemins des modules) ? */
    protected function hasPendingMigrations(Migrator $migrator): bool
    {
        $repository = $migrator->getRepository();
        // Base vierge : tout est en attente, mais il n'y a rien à sauvegarder.
        if (! $repository->repositoryExists()) {
            // Pas de table `migrations` : base vierge… OU base héritée qui contient
            // déjà des données et que migrate va toucher. Seule la première se passe de dump.
            return $this->databaseHasTables();
        }

        $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
        $ran   = $repository->getRan();

        foreach (array_keys($files) as $name) {
            if (! in_array($name, $ran, true)) {
                return true;
            }
        }

        return false;
    }

    protected function databaseHasTables(): bool
    {
        try {
            return count(\Illuminate\Support\Facades\DB::select('SHOW TABLES')) > 0;
        } catch (\Throwable $e) {
            return true; // doute : mieux vaut une sauvegarde de trop
        }
    }

    /** Lance le dump, compressé à la volée dans $target. Retourne l'erreur, ou null. */
    protected function dump(array $conn, string $target, int $timeout): ?string
    {
        $args = ['--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
                 '--default-character-set='.($conn['charset'] ?? 'utf8mb4')];

        if (! empty($conn['unix_socket'])) {
            $args[] = '--socket='.$conn['unix_socket'];
        } else {
            $args[] = '--host='.($conn['host'] ?? '127.0.0.1');
            $args[] = '--port='.($conn['port'] ?? 3306);
        }
        $args[] = '--user='.($conn['username'] ?? '');
        $args[] = (string) ($conn['database'] ?? '');

        $out = @gzopen($target, 'wb6');
        if ($out === false) {
            return "fichier de sauvegarde non inscriptible : $target";
        }
        @chmod($target, 0600);

        $stderr = '';
        $code   = $this->runProcess($this->binary(), $args, ['MYSQL_PWD' => (string) ($conn['password'] ?? '')],
            $timeout, function (string $type, string $buffer) use ($out, &$stderr) {
                if ($type === Process::OUT) {
                    gzwrite($out, $buffer);
                } else {
                    $stderr .= $buffer;
                }
            });
        gzclose($out);

        if ($code !== 0) {
            // stderr ne contient jamais le mot de passe (il n'est pas dans argv),
            // mais on borne quand même ce qu'on remonte dans les journaux.
            return "le dump a retourné $code — ".trim(substr($stderr, 0, 500));
        }

        return null;
    }

    /** Binaire de dump : mysqldump, sinon mariadb-dump. */
    protected function binary(): string
    {
        foreach (['mysqldump', 'mariadb-dump'] as $b) {
            $path = trim((string) @shell_exec('command -v '.escapeshellarg($b).' 2>/dev/null'));
            if ($path !== '') {
                return $path;
            }
        }

        return 'mysqldump';
    }

    /**
     * Exécute le processus (point d'injection des tests). Retourne le code de sortie ;
     * un binaire absent ou un délai dépassé est un échec (code non nul), jamais une exception.
     */
    protected function runProcess(string $binary, array $args, array $env, int $timeout, callable $onOutput): int
    {
        try {
            $p = new Process(array_merge([$binary], $args), null, $env, null, $timeout);
            $p->run($onOutput);

            return (int) $p->getExitCode();
        } catch (\Throwable $e) {
            $onOutput(Process::ERR, $e->getMessage());

            return 127;
        }
    }

    /** Le gzip se lit jusqu'au bout et le dump est allé jusqu'à son pied de page. */
    protected function verify(string $file): ?string
    {
        $in = @gzopen($file, 'rb');
        if ($in === false) {
            return 'sauvegarde illisible';
        }
        $tail = '';
        $size = 0;
        while (! gzeof($in)) {
            $chunk = gzread($in, 1 << 20);
            if ($chunk === false) {
                gzclose($in);

                return 'archive corrompue (lecture interrompue)';
            }
            $size += strlen($chunk);
            $tail = substr($tail.$chunk, -1024);
        }
        gzclose($in);

        if ($size === 0) {
            return 'sauvegarde vide';
        }
        if (! str_contains($tail, self::FOOTER)) {
            return 'dump incomplet (pied de page absent)';
        }

        return null;
    }

    /** Ne garde que les $keep plus récentes sauvegardes de CETTE commande. */
    protected function prune(string $dir, int $keep, string $justMade): void
    {
        // Horodatage strict : un fichier nommé à la main n'est jamais ni compté ni supprimé.
        $files = array_values(array_filter(glob($dir.'/'.self::PREFIX.'*'.self::SUFFIX) ?: [],
            fn ($f) => preg_match('/^tagtoa-db-\d{8}-\d{6}\.sql\.gz$/', basename($f)) === 1));
        rsort($files); // le nom porte l'horodatage : l'ordre alphabétique est chronologique
        foreach (array_slice($files, $keep) as $old) {
            if ($old !== $justMade && @unlink($old)) {
                $this->line('Ancienne sauvegarde supprimée : '.basename($old));
            }
        }
    }
}
