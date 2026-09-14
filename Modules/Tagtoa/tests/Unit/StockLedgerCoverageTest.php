<?php

namespace Modules\Tagtoa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le stock ne s'écrit QUE par le journal.
 *
 * Le stock était modifié depuis quatre endroits. Quand il ne correspondait
 * plus à l'étagère, personne ne pouvait remonter le fil. Le journal
 * (Services/Inventory/StockLedger) est maintenant le seul chemin.
 *
 * Ce test est une garde : il échoue si quelqu'un — moi dans six mois, un autre
 * développeur, une fusion mal résolue — remet une écriture directe. Sans lui,
 * le journal redeviendrait faux en silence, et un journal auquel on ne peut
 * pas se fier ne vaut pas mieux que pas de journal du tout.
 */
class StockLedgerCoverageTest extends TestCase
{
    /**
     * Le seul fichier autorisé à écrire une QUANTITÉ de stock.
     *
     * Écrire `'stock' => null` reste permis partout : ce n'est pas une
     * quantité, c'est arrêter de compter (article illimité). Le journal n'a
     * rien à en dire, et l'interdire obligerait à une exception par écran.
     */
    private const AUTORISES = [
        'app/Services/Inventory/StockLedger.php',
    ];

    public function test_nobody_writes_stock_behind_the_ledger(): void
    {
        $fautifs = [];

        foreach ($this->fichiersPhp() as $chemin => $code) {
            if (in_array($chemin, self::AUTORISES, true)) {
                continue;
            }

            // decrement('stock') / increment('stock') : l'écriture directe
            // historique, celle qui contournait tout.
            if (preg_match('/->(?:in|de)crement\(\s*[\'"]stock[\'"]/', $code)) {
                $fautifs[] = $chemin.' — increment/decrement direct';
            }

            // ->stock = … : l'affectation nue.
            if (preg_match('/->stock\s*=[^=]/', $code)) {
                $fautifs[] = $chemin.' — affectation directe';
            }

            // Écriture par tableau, mais SEULEMENT à travers un verbe qui
            // enregistre : update / fill / forceFill / create. La clé « stock »
            // apparaît légitimement ailleurs — déclarations de cast, règles de
            // validation, tableaux d'affichage, attributs remis à un service
            // qui, lui, passera par le journal.
            //
            // « => null » reste permis : arrêter de compter n'est pas une
            // quantité, et le journal n'a rien à en dire.
            if (preg_match('/(?:->update|->fill|->forceFill|->create|::create)\(\s*\[[^\]]{0,600}?[\'"]stock[\'"]\s*=>(?!\s*null\b)/s', $code)) {
                $fautifs[] = $chemin.' — écriture par tableau';
            }
        }

        $this->assertSame([], $fautifs, implode("\n", array_merge(
            ['Le stock doit passer par StockLedger (voir Services/Inventory/StockLedger) :'],
            $fautifs,
            ['', 'Si une exception est vraiment justifiée, ajoutez le fichier à self::AUTORISES', 'ET dites pourquoi en commentaire.']
        )));
    }

    public function test_the_allow_list_stays_short(): void
    {
        // Une liste d'exceptions qui s'allonge est une règle qui se dissout.
        // Si elle doit grandir, c'est le signe qu'il manque une méthode au
        // journal, pas qu'il faut assouplir la garde.
        $this->assertLessThanOrEqual(1, count(self::AUTORISES),
            'Trop de fichiers écrivent le stock hors du journal.');
    }

    /** @return array<string,string> chemin relatif => contenu */
    private function fichiersPhp(): array
    {
        $racine = dirname(__DIR__, 2);
        $fichiers = [];

        foreach (['app'] as $dossier) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($racine.'/'.$dossier, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->getExtension() !== 'php') {
                    continue;
                }
                $relatif = str_replace($racine.'/', '', $f->getPathname());
                $fichiers[$relatif] = file_get_contents($f->getPathname());
            }
        }

        return $fichiers;
    }
}
