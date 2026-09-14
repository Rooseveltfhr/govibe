<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Pos\CatalogRef;
use PHPUnit\Framework\TestCase;

/**
 * Désigner un article sans jamais confondre deux catalogues.
 *
 * Le plat n°7 du menu et le bouton n°7 de la caisse sont deux choses
 * différentes : les deux listes ont leurs propres identifiants, qui commencent
 * tous les deux à 1. Sans discriminant, vendre « l'article 7 » au comptoir
 * retirerait du stock au hasard dans l'une ou l'autre.
 */
class CatalogRefTest extends TestCase
{
    public function test_a_reference_always_carries_where_it_comes_from(): void
    {
        $this->assertSame('menu:7', CatalogRef::make('menu', 7));
        $this->assertSame('pos:7', CatalogRef::make('pos', 7));
    }

    public function test_the_same_number_in_two_catalogues_is_two_different_things(): void
    {
        // Le cœur du problème.
        $plat   = CatalogRef::make('menu', 7);
        $bouton = CatalogRef::make('pos', 7);

        $this->assertNotSame($plat, $bouton);
        $this->assertSame('menu', CatalogRef::sourceOf($plat));
        $this->assertSame('pos', CatalogRef::sourceOf($bouton));
        $this->assertSame(7, CatalogRef::idOf($plat));
        $this->assertSame(7, CatalogRef::idOf($bouton));
    }

    public function test_a_till_already_installed_keeps_working(): void
    {
        // Les caisses déjà en service envoient un identifiant nu. Une mise à
        // jour de l'application ne doit pas interrompre une vente en cours.
        $this->assertSame(['pos', 7], CatalogRef::parse(7));
        $this->assertSame(['pos', 7], CatalogRef::parse('7'));
    }

    public function test_an_invented_reference_designates_nothing(): void
    {
        foreach (['', 'menu:', ':7', 'menu:abc', 'stock:7', 'menu:-1', 'menu:0', null, [], 0, -3] as $mauvais) {
            $this->assertNull(CatalogRef::parse($mauvais),
                'Une référence sans signification ne doit désigner aucun article.');
            $this->assertFalse(CatalogRef::isValid($mauvais));
        }
    }

    public function test_an_unknown_catalogue_never_becomes_a_third_one(): void
    {
        // make() ne doit pas fabriquer une source qui n'existe pas : le reste du
        // code ne saurait pas où chercher l'article.
        $this->assertSame('pos:7', CatalogRef::make('entrepot', 7));
        $this->assertSame('menu:7', CatalogRef::make('  MENU  ', 7));
    }

    public function test_a_reference_survives_a_round_trip(): void
    {
        foreach ([['menu', 1], ['pos', 42], ['menu', 999999]] as [$source, $id]) {
            [$s, $i] = CatalogRef::parse(CatalogRef::make($source, $id));
            $this->assertSame($source, $s);
            $this->assertSame($id, $i);
        }
    }
}
