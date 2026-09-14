<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Models\Stand\StandBatch;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandScratch;

/**
 * TAGTOA — fabriquer un lot de Smart Stands.
 *
 *   php artisan tagtoa:stands:mint --batch=TAGTOA-2026-001 --count=10000
 *
 * Produit quatre fichiers. Trois sont sans risque et partent chez l'imprimeur
 * et l'encodeur ; le quatrième contient les codes en clair.
 *
 * ⚠️ `secrets.csv` COMPROMET UN LOT ENTIER — dix mille commerces — en un seul
 * fichier. C'est le maillon faible de toute l'architecture, plus que le QR, la
 * puce ou l'identifiant. Il est écrit en 0600, il doit voyager par un canal
 * contrôlé, et être détruit à la confirmation du tirage. S'il survit « au cas
 * où », il finira dans une sauvegarde ou un courriel.
 */
class MintStandsCommand extends Command
{
    protected $signature = 'tagtoa:stands:mint
                            {--batch= : Code du lot, ex. TAGTOA-2026-001}
                            {--count=100 : Nombre de stands}
                            {--prefix=TG : Préfixe imprimé}
                            {--start=1 : Premier numéro de série}
                            {--manufacturer= : Nom de l\'imprimeur}
                            {--out= : Dossier des fichiers de production}
                            {--url=https://tagtoa.com : Domaine imprimé — IRRÉVERSIBLE}';

    protected $description = 'Fabrique un lot de Smart Stands (identifiants, secrets, fichiers de production)';

    public function handle(StandMinter $minter): int
    {
        $code = (string) $this->option('batch');
        if ($code === '') {
            $this->error('Un lot doit porter un code : --batch=TAGTOA-2026-001');

            return self::FAILURE;
        }

        if (StandBatch::where('code', $code)->exists()) {
            // Refabriquer un lot existant produirait des identifiants en double
            // — deux objets physiques pour une seule ligne.
            $this->error("Le lot « $code » existe déjà.");

            return self::FAILURE;
        }

        $count  = max(1, (int) $this->option('count'));
        $prefix = (string) $this->option('prefix');
        $start  = max(1, (int) $this->option('start'));
        $url    = rtrim((string) $this->option('url'), '/');

        $dossier = (string) ($this->option('out') ?: storage_path('app/tagtoa/batches/'.$code));

        $this->info("Fabrication de $count stands — lot $code");
        $this->line('  Domaine imprimé : '.$url.'  (irréversible une fois gravé)');
        $this->newLine();

        $resultat = $minter->mint($code, $count, $prefix, $start, [
            'manufacturer' => $this->option('manufacturer'),
        ]);

        $this->ecrireFichiers($dossier, $resultat['batch'], $resultat['secrets'], $url);

        // Le clair disparaît de la mémoire du processus dès qu'il est écrit.
        $resultat['secrets'] = null;
        unset($resultat['secrets']);

        $this->newLine();
        $this->info("Lot $code fabriqué : ".StandId::format($start, $prefix)
            .' → '.StandId::format($start + $count - 1, $prefix));
        $this->line('  Fichiers : '.$dossier);
        $this->newLine();
        $this->warn('secrets.csv contient les codes EN CLAIR. Canal contrôlé, puis destruction après tirage.');
        $this->newLine();
        $this->line('  Sous le panneau à gratter, l\'imprimeur rend DEUX choses :');
        $this->line('    • activation_code — les huit caractères lisibles (référence, jamais retirée)');
        $this->line('    • scan_payload    — un QR ; c\'est lui qui fait passer une salle de');
        $this->line('                        quarante tables de deux heures à quatre minutes');

        return self::SUCCESS;
    }

    /** @param array<string,string> $secrets */
    private function ecrireFichiers(string $dossier, StandBatch $batch, array $secrets, string $url): void
    {
        if (! is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }

        $empreintes = [];

        // 1) Ce qui s'imprime au recto — sans aucun secret.
        $lignes = ["public_id,url"];
        foreach (array_keys($secrets) as $publicId) {
            $lignes[] = $publicId.','.$url.StandId::path($publicId);
        }
        $empreintes['stands.csv'] = $this->ecrire($dossier.'/stands.csv', implode("\n", $lignes), 0644);

        // 2) Ce qui s'écrit dans la puce : la MÊME URL. Pas l'identifiant nu —
        //    un identifiant nu exigerait une application pour être interprété,
        //    alors qu'une URL ouvre le navigateur de n'importe quel téléphone.
        $lignes = ["public_id,ndef_uri,lock"];
        foreach (array_keys($secrets) as $publicId) {
            $lignes[] = $publicId.','.$url.StandId::path($publicId).',readonly';
        }
        $empreintes['nfc.csv'] = $this->ecrire($dossier.'/nfc.csv', implode("\n", $lignes), 0644);

        // 3) LE FICHIER SENSIBLE. 0600 : lisible par son seul propriétaire.
        //
        //    Deux colonnes pour UNE seule information, imprimées côte à côte
        //    sous le panneau à gratter :
        //
        //      activation_code — les huit caractères lisibles. Ils restent la
        //        référence : un panneau abîmé au grattage, une caméra cassée, un
        //        téléphone sans autorisation — et le stand doit rester activable.
        //
        //      scan_payload    — le contenu du petit QR imprimé à côté. C'est
        //        lui qui fait passer l'activation d'une salle de quarante tables
        //        de deux heures à quatre minutes. Il porte l'identifiant ET le
        //        secret, donc une seule visée suffit.
        //
        //    Ce QR-ci est SOUS le panneau : il n'est visible que de celui qui
        //    tient l'objet. Rien n'est affaibli — seule la saisie change.
        $lignes = ["public_id,activation_code,scan_payload"];
        foreach ($secrets as $publicId => $secret) {
            $lignes[] = $publicId.','.StandId::pretty($secret).','.StandScratch::payload($publicId, $secret);
        }
        $empreintes['secrets.csv'] = $this->ecrire($dossier.'/secrets.csv', implode("\n", $lignes), 0600);

        // 4) Le manifeste : la preuve qu'on imprime bien ce qui a été généré.
        //    Un fichier modifié en chemin donnerait des objets physiques qui ne
        //    correspondent à rien en base.
        $manifeste = [
            'batch'        => $batch->code,
            'quantity'     => $batch->quantity,
            'prefix'       => $batch->id_prefix,
            'range'        => [$batch->range_start, $batch->range_end],
            'hardware'     => $batch->hardware,
            'manufacturer' => $batch->manufacturer,
            'printed_url'  => $url,
            'secret_bits'  => StandId::entropyBits(),
            // Ce que l'imprimeur doit rendre sous le panneau à gratter. Écrit
            // dans le manifeste parce qu'un lot imprimé sans le QR de secret
            // resterait activable — mais une table à la fois, et le marchand
            // le découvrirait seulement le carton ouvert.
            'scratch_panel' => [
                'human_readable' => 'activation_code',
                'scan_symbol'    => 'qr',
                'scan_source'    => 'scan_payload',
                'separator'      => StandScratch::SEPARATOR,
            ],
            'files'        => $empreintes,
            'generated_at' => now()->toIso8601String(),
        ];

        $json = json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->ecrire($dossier.'/manifest.json', $json, 0644);

        $batch->forceFill(['manifest_sha256' => hash('sha256', $json)])->save();

        foreach (['stands.csv', 'nfc.csv', 'secrets.csv', 'manifest.json'] as $f) {
            $this->line('  écrit  '.$f);
        }
    }

    /** Écrit un fichier et renvoie son empreinte. */
    private function ecrire(string $chemin, string $contenu, int $mode): string
    {
        file_put_contents($chemin, $contenu."\n");
        chmod($chemin, $mode);

        return hash('sha256', $contenu);
    }
}
