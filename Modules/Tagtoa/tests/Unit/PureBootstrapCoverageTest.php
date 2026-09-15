<?php

namespace Modules\Tagtoa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou : la suite Unit tourne SANS Laravel, sur un chargeur écrit à la main.
 *
 * `tests/bootstrap.php` (à la racine du dépôt) liste un par un les fichiers de
 * logique pure à charger — il n'y a pas d'autochargement, parce qu'il n'y a pas
 * de framework. Une classe nouvelle testée sans être inscrite dans cette liste
 * passe LOCALEMENT, où la suite Feature charge Laravel, et casse en CI, où la
 * suite Unit tourne seule. C'est exactement ce qui vient d'arriver à
 * `TransferCode` : vingt minutes perdues pour une ligne oubliée.
 *
 * Ce test transforme l'oubli en échec immédiat, avec le remède dans le message.
 */
class PureBootstrapCoverageTest extends TestCase
{
    private function bootstrap(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../../tests/bootstrap.php');
    }

    public function test_every_module_class_used_by_a_unit_test_is_loaded_by_the_pure_bootstrap(): void
    {
        $amorce = $this->bootstrap();
        $this->assertNotSame('', $amorce, 'Chemin du bootstrap cassé ?');

        $manquants = [];
        $vus = 0;

        foreach (glob(__DIR__.'/*.php') ?: [] as $fichier) {
            $code = (string) file_get_contents($fichier);

            preg_match_all('/^use Modules\\\\Tagtoa\\\\App\\\\([A-Za-z0-9_\\\\]+);/m', $code, $m);

            foreach ($m[1] as $classe) {
                $chemin = str_replace('\\', '/', $classe).'.php';
                $vus++;

                if (! str_contains($amorce, "/".$chemin."'")) {
                    $manquants[] = basename($fichier).' → '.$chemin;
                }
            }
        }

        sort($manquants);

        $this->assertSame([], $manquants, "\n".
            "Ces classes sont utilisées par un test unitaire mais ne sont PAS chargées\n".
            "par tests/bootstrap.php — la suite passera ici et cassera en CI :\n  - ".
            implode("\n  - ", $manquants)."\n\n".
            "Ajoutez dans tests/bootstrap.php :\n".
            "    require_once \$base.'/<chemin>';\n");

        // Garde-fou du garde-fou : un scan vide passerait en silence si le
        // motif ou le dossier changeait.
        $this->assertGreaterThan(25, $vus, 'Scan des tests unitaires vide ou motif cassé ?');
    }
}
