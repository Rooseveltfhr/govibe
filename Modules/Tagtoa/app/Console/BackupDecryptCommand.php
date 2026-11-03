<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Services\Backup\OffsiteCrypto;

/**
 * php artisan tagtoa:backup-decrypt <fichier.enc> [--out=fichier]
 *
 * BK-1b — déchiffre une copie hors site (à lancer sur VOTRE poste, ou sur un serveur de secours, avec la clé privée).
 * La clé privée est lue sur l'entrée (saisie masquée) ou dans la variable d'environnement TAGTOA_OFFSITE_PRIVATE_KEY, JAMAIS dans
 * un argument de ligne de commande (visible par `ps` et l'historique du shell). Un fichier modifié ou tronqué est refusé.
 */
class BackupDecryptCommand extends Command
{
    protected $signature = 'tagtoa:backup-decrypt
                            {file : Fichier .enc téléchargé depuis le bucket}
                            {--out= : Fichier de sortie (défaut : le nom sans .enc)}';

    protected $description = 'Déchiffre une copie hors site avec la clé privée';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error('Fichier introuvable : '.$file);

            return self::FAILURE;
        }
        $out = (string) ($this->option('out') ?: preg_replace('/\.enc$/', '', $file));
        if ($out === $file) {
            $out .= '.dec';
        }
        if (file_exists($out)) {
            $this->error('Le fichier de sortie existe déjà (rien n\'est écrasé) : '.$out);

            return self::FAILURE;
        }

        $b64 = (string) (getenv('TAGTOA_OFFSITE_PRIVATE_KEY') ?: $this->secret('Clé privée (base64, saisie masquée)'));
        try {
            OffsiteCrypto::decryptFile($file, $out, OffsiteCrypto::privateKeyFromBase64($b64));
        } catch (\Throwable $e) {
            $this->error('Déchiffrement refusé : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Déchiffré : '.$out.' ('.number_format((int) filesize($out)).' octets)');

        return self::SUCCESS;
    }
}
