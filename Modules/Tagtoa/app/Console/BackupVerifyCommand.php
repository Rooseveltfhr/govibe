<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan tagtoa:backup-verify [--dir=] [--min-ratio=0.5]
 *
 * Relit la DERNIÈRE sauvegarde faite par tagtoa:backup-db et dit si elle a l'air saine. LECTURE SEULE : ne touche ni la base ni la
 * sauvegarde. Vérifie : l'archive se lit jusqu'au bout et le dump est complet ; CHAQUE table de la base y figure (une table
 * absente = une sauvegarde qui ne restaurerait pas tout) ; la taille n'a pas chuté par rapport à la précédente ; la place prise.
 *
 * ⚠️ Ce n'est PAS une restauration : rien n'est rejoué. La restauration réelle (vrai mysqldump → vraie base MySQL 8 → comparaison)
 * est prouvée à chaque PR par le test d'intégration « sauvegarde » de la CI ; ici on contrôle le fichier RÉEL du serveur.
 *
 * Code de sortie : 0 = saine ; 1 = problème. Le déploiement l'appelle sans jamais se bloquer dessus.
 */
class BackupVerifyCommand extends Command
{
    protected $signature = 'tagtoa:backup-verify
                            {--dir= : Dossier des sauvegardes (défaut : storage/app/backups)}
                            {--max-age=0 : Alerte si la dernière sauvegarde a plus de N minutes (0 = pas de contrôle ; utilisé par le déploiement)}
                            {--min-ratio=0.5 : Alerte si la dernière pèse moins de ce rapport de la précédente}';

    protected $description = 'Vérifie (lecture seule) la dernière sauvegarde de la base : complète, toutes les tables, taille plausible.';

    public function handle(): int
    {
        $dir = rtrim((string) ($this->option('dir') ?: storage_path('app/backups')), '/');
        $fichiers = BackupDatabaseCommand::listBackups($dir);
        if ($fichiers === []) {
            $this->error('Aucune sauvegarde trouvée dans '.$dir.'.');

            return self::FAILURE;
        }

        $dernier = $fichiers[0];
        $age = BackupDatabaseCommand::stampOf($dernier);
        $this->line('Dernière sauvegarde : '.basename($dernier).' ('.number_format((int) filesize($dernier)).' octets, il y a '.$age->diffForHumans(null, true).')');

        $problemes = 0;

        // Fraîcheur : si la sauvegarde du déploiement a échoué, la « dernière » est une vieille — ne pas la prendre pour celle d'aujourd'hui.
        $maxAge = (int) $this->option('max-age');
        if ($maxAge > 0 && $age->diffInMinutes(now(), false) > $maxAge) {
            $problemes++;
            $this->error('   PROBLÈME : la dernière sauvegarde date de plus de '.$maxAge.' min — aucune sauvegarde récente pour ce déploiement.');
        }

        if (($err = BackupDatabaseCommand::verifyFile($dernier)) !== null) {
            $this->error('   PROBLÈME : '.$err);

            return self::FAILURE;
        }
        $this->line('   archive lisible jusqu\'au bout, dump complet : oui');

        // Toutes les tables de la base doivent figurer dans le dump.
        $dansDump = $this->tablesDuDump($dernier);
        try {
            $vives = array_values(array_filter(Schema::getTableListing(), fn ($t) => ! str_starts_with((string) $t, 'sqlite_')));
        } catch (\Throwable $e) {
            $vives = [];
            $this->warn('   liste des tables vivantes indisponible : comparaison des tables ignorée.');
        }
        if ($vives !== []) {
            $vives = array_map(fn ($t) => preg_replace('/^.*\./', '', (string) $t), $vives);   // « schéma.table » → « table »
            $absentes = array_values(array_diff($vives, $dansDump));
            if ($absentes !== []) {
                $problemes++;
                $this->error('   PROBLÈME : '.count($absentes).' table(s) de la base absente(s) du dump : '.implode(', ', array_slice($absentes, 0, 10)).(count($absentes) > 10 ? ' …' : ''));
            } else {
                $this->line('   tables de la base présentes dans le dump : '.count($vives).' sur '.count($vives));
            }
        }

        // Taille plausible par rapport à la précédente.
        if (isset($fichiers[1])) {
            $avant = (int) filesize($fichiers[1]);
            $ratio = (float) $this->option('min-ratio');
            if ($avant > 0 && filesize($dernier) < $avant * $ratio) {
                $problemes++;
                $this->error('   PROBLÈME : la dernière sauvegarde pèse '.number_format((int) filesize($dernier)).' octets contre '.number_format($avant).' pour la précédente (moins de '.(int) ($ratio * 100).' %).');
            } else {
                $this->line('   taille cohérente avec la précédente : oui');
            }
        } else {
            $this->line('   pas de sauvegarde précédente à comparer.');
        }

        // Images (BK-1c) : s'il y a des archives, la dernière doit être lisible jusqu'au bout. Absence = pas un problème (rien à archiver / jamais lancé).
        $images = BackupUploadsCommand::listBackups($dir);
        if ($images !== []) {
            if (($err = BackupUploadsCommand::verifyFile($images[0])) !== null) {
                $problemes++;
                $this->error('   PROBLÈME (images) : '.basename($images[0]).' — '.$err);
            } else {
                $this->line('   images : '.basename($images[0]).' lisible jusqu\'au bout ('.number_format((int) filesize($images[0])).' octets)');
            }
        }

        $total = array_sum(array_map('filesize', $fichiers)) + array_sum(array_map('filesize', $images));
        $this->line('Sauvegardes conservées : '.count($fichiers).' · place prise : '.number_format((int) round($total / 1048576, 1), 0).' Mo');
        $this->line('(Contrôle du fichier, pas une restauration : la restauration réelle est testée en CI sur MySQL 8.)');

        return $problemes === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** Noms des tables créées par le dump (lecture en flux, mémoire constante). @return string[] */
    protected function tablesDuDump(string $file): array
    {
        $noms = [];
        $in = @gzopen($file, 'rb');
        if ($in === false) {
            return [];
        }
        while (! gzeof($in)) {
            $ligne = gzgets($in, 65536);
            if ($ligne === false) {
                break;
            }
            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?[`"]([^`"]+)[`"]/', $ligne, $m)) {
                $noms[$m[1]] = true;
            }
        }
        gzclose($in);

        return array_keys($noms);
    }
}
