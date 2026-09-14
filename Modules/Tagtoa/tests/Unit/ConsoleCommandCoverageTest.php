<?php

namespace Modules\Tagtoa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les commandes testées sont celles que la production enregistre.
 *
 * Le harnais de test ne peut pas booter TagtoaServiceProvider — il dépend de
 * `module_path()` et surcharge des vues du cœur Biztap, absent de ce dépôt. Les
 * commandes sont donc listées à DEUX endroits, et deux listes finissent
 * toujours par diverger : une commande ajoutée en production et oubliée côté
 * test ne serait jamais exécutée par un test, et personne ne s'en apercevrait
 * avant qu'elle ne casse sur le serveur.
 */
class ConsoleCommandCoverageTest extends TestCase
{
    public function test_both_lists_register_the_same_commands(): void
    {
        $this->assertSame(
            $this->commandesDe(dirname(__DIR__, 2).'/app/Providers/TagtoaServiceProvider.php'),
            $this->commandesDe(dirname(__DIR__).'/Stubs/ConsoleProvider.php'),
            "La liste des commandes de TagtoaServiceProvider et celle du harnais de test ont divergé."
        );
    }

    public function test_every_registered_command_exists(): void
    {
        $racine = dirname(__DIR__, 2);

        foreach ($this->commandesDe($racine.'/app/Providers/TagtoaServiceProvider.php') as $classe) {
            $chemin = $racine.'/app/Console/'.$classe.'.php';
            $this->assertFileExists($chemin, "La commande « $classe » est enregistrée mais n'existe pas.");
        }
    }

    /**
     * Noms courts des classes de commande citées dans un fichier.
     *
     * On lit le SOURCE plutôt que d'instancier : le provider de production
     * n'est pas chargeable ici, et c'est précisément la raison de ce test.
     *
     * @return array<int,string>
     */
    private function commandesDe(string $chemin): array
    {
        $code = file_get_contents($chemin);
        preg_match_all('/(\w+Command)::class/', $code, $m);

        $noms = array_values(array_unique($m[1]));
        sort($noms);

        return $noms;
    }
}
