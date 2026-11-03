<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Services\Backup\OffsiteCrypto;
use Modules\Tagtoa\App\Services\Backup\S3Client;
use Modules\Tagtoa\App\Services\Backup\S3Transport;

/**
 * php artisan tagtoa:backup-offsite [--dir=]
 *
 * BK-1b — envoie la DERNIÈRE sauvegarde de la base et la DERNIÈRE archive d'images, chiffrées, vers un bucket S3-compatible
 * (Cloudflare R2, Backblaze B2…). Rien ne part en clair : le fichier est chiffré localement avec la clé PUBLIQUE (la clé privée
 * n'est jamais sur le serveur), envoyé avec son SHA-256 signé, puis relu (taille + ETag) pour confirmer. Un fichier déjà présent
 * au bon poids n'est pas renvoyé. INACTIVE tant que TAGTOA_OFFSITE_* n'est pas renseigné (ce n'est pas une erreur).
 *
 * Code de sortie : 0 = tout est hors site (ou inactive) ; 1 = au moins un échec.
 * Aucun secret n'est jamais affiché.
 */
class BackupOffsiteCommand extends Command
{
    protected $signature = 'tagtoa:backup-offsite
                            {--dir= : Dossier des sauvegardes (défaut : storage/app/backups)}
                            {--timeout=600 : Durée maximale d\'un envoi, en secondes}';

    protected $description = 'Envoie (chiffrées) les dernières sauvegardes vers un bucket S3-compatible (R2, B2…)';

    public function handle(): int
    {
        $cfg = (array) config('tagtoa.offsite', []);
        $required = ['endpoint', 'bucket', 'key_id', 'secret', 'public_key'];
        $missing = array_values(array_filter($required, fn ($k) => trim((string) ($cfg[$k] ?? '')) === ''));
        if (count($missing) === count($required)) {
            $this->info('Copie hors site non configurée (TAGTOA_OFFSITE_*) — étape ignorée.');

            return self::SUCCESS;
        }
        if ($missing !== []) {
            $this->error('Copie hors site MAL configurée, variable(s) manquante(s) : '.implode(', ', array_map(fn ($k) => 'TAGTOA_OFFSITE_'.strtoupper($k), $missing)));

            return self::FAILURE;
        }

        try {
            $publicKey = OffsiteCrypto::publicKeyFromBase64((string) $cfg['public_key']);
            $client = $this->client($cfg);
        } catch (\Throwable $e) {
            $this->error('Copie hors site : configuration invalide — '.$e->getMessage());

            return self::FAILURE;
        }

        $dir = rtrim((string) ($this->option('dir') ?: storage_path('app/backups')), '/');
        if (! is_dir($dir)) {
            $this->error('Dossier des sauvegardes introuvable : '.$dir);

            return self::FAILURE;
        }

        $lock = @fopen($dir.'/.tagtoa-offsite.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->info('Un envoi hors site est déjà en cours — celui-ci est ignoré.');

            return self::SUCCESS;
        }

        try {
            $candidats = array_filter([
                BackupDatabaseCommand::listBackups($dir)[0] ?? null,
                BackupUploadsCommand::listBackups($dir)[0] ?? null,
            ]);
            if ($candidats === []) {
                $this->error('Aucune sauvegarde locale à envoyer dans '.$dir.'.');

                return self::FAILURE;
            }

            $prefix = ltrim((string) ($cfg['prefix'] ?? ''), '/');
            $prefix = $prefix === '' ? '' : rtrim($prefix, '/').'/';
            $echecs = 0;
            foreach ($candidats as $file) {
                $echecs += $this->envoyer($client, $publicKey, $file, $prefix.basename($file).'.enc', $dir) ? 0 : 1;
            }

            return $echecs === 0 ? self::SUCCESS : self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Point d'injection des tests. */
    protected function client(array $cfg): S3Client
    {
        return new S3Client((string) $cfg['endpoint'], (string) $cfg['bucket'], (string) $cfg['key_id'], (string) $cfg['secret'],
            (string) ($cfg['region'] ?: 'auto'), (int) $this->option('timeout'),
            $this->laravel->bound(S3Transport::class) ? $this->laravel->make(S3Transport::class) : null);
    }

    /** Place libre (point d'injection des tests). */
    protected function freeBytes(string $dir): float|false
    {
        return @disk_free_space($dir);
    }

    private function envoyer(S3Client $client, string $publicKey, string $file, string $objet, string $dir): bool
    {
        $attendu = OffsiteCrypto::encryptedSize((int) filesize($file));
        $tmp = $dir.'/.offsite-'.bin2hex(random_bytes(4)).'.tmp';
        try {
            $deja = $client->head($objet);
            if ($deja !== null && $deja['size'] === $attendu) {
                $this->info('Déjà hors site : '.$objet.' ('.number_format($attendu).' octets)');

                return true;
            }

            $libre = $this->freeBytes($dir);
            if ($libre !== false && $libre < $attendu * 1.1) {
                throw new \RuntimeException('place disque insuffisante pour la copie chiffrée temporaire');
            }

            OffsiteCrypto::encryptFile($file, $tmp, $publicKey);
            if ((int) filesize($tmp) !== $attendu) {
                throw new \RuntimeException('taille chiffrée inattendue (bug)');
            }
            $etag = $client->putFile($objet, $tmp);

            $apres = $client->head($objet);
            if ($apres === null || $apres['size'] !== $attendu) {
                throw new \RuntimeException('relecture : l\'objet est absent ou de mauvaise taille');
            }
            // Pour un envoi simple, l'ETag est le MD5 du corps ; on le compare quand il a cette forme.
            if (preg_match('/^[0-9a-f]{32}$/i', $etag) === 1 && ! hash_equals(strtolower($etag), md5_file($tmp))) {
                throw new \RuntimeException('relecture : empreinte différente (ETag)');
            }

            $this->info('Hors site OK : '.$objet.' ('.number_format($attendu).' octets, chiffré)');

            return true;
        } catch (\Throwable $e) {
            $this->error('Copie hors site ÉCHOUÉE pour '.basename($file).' : '.$e->getMessage());

            return false;
        } finally {
            @unlink($tmp);
        }
    }
}
