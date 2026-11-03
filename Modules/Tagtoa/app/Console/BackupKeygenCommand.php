<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Services\Backup\OffsiteCrypto;

/**
 * php artisan tagtoa:backup-keygen
 *
 * BK-1b — fabrique la paire de clés de la copie hors site. À lancer SUR VOTRE POSTE (pas sur le serveur) : la clé PRIVÉE
 * ne doit jamais se trouver sur le serveur. Elle est affichée UNE fois, nulle part enregistrée.
 *  - clé PUBLIQUE  → variable TAGTOA_OFFSITE_PUBLIC_KEY du serveur (elle ne sait que chiffrer) ;
 *  - clé PRIVÉE    → gestionnaire de mots de passe + copie papier. Perdue = copies hors site définitivement illisibles.
 */
class BackupKeygenCommand extends Command
{
    protected $signature = 'tagtoa:backup-keygen';

    protected $description = 'Génère la paire de clés (publique/privée) de la copie hors site chiffrée';

    public function handle(): int
    {
        $kp = OffsiteCrypto::generateKeypair();
        $this->line('');
        $this->info('CLÉ PUBLIQUE (à mettre sur le serveur : TAGTOA_OFFSITE_PUBLIC_KEY) :');
        $this->line($kp['public']);
        $this->line('');
        $this->warn('CLÉ PRIVÉE (à garder HORS du serveur — gestionnaire de mots de passe + copie papier) :');
        $this->line($kp['private']);
        $this->line('');
        $this->warn('Elle n\'est affichée qu\'une fois et enregistrée nulle part. Sans elle, les copies hors site sont illisibles pour toujours.');
        $this->warn('Ne la collez jamais dans le .env du serveur, un ticket, un chat ou un dépôt.');

        return self::SUCCESS;
    }
}
