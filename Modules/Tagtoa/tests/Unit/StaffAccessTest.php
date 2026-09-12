<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use PHPUnit\Framework\TestCase;

/**
 * Qui a le droit de faire quoi dans un commerce.
 *
 * Règle posée par Roosevelt : le caissier vend et répond de SES ventes ; seul
 * le patron voit l'ensemble des caisses et décide du catalogue.
 */
class StaffAccessTest extends TestCase
{
    public function test_the_owner_can_do_everything(): void
    {
        foreach (StaffAccess::ABILITIES as $ability) {
            $this->assertTrue(StaffAccess::can(StaffAccess::ROLE_OWNER, $ability),
                "Le patron doit pouvoir : $ability");
        }
    }

    public function test_a_cashier_sells_but_never_touches_the_catalogue(): void
    {
        $caissier = StaffAccess::ROLE_CASHIER;

        // Ce qu'il fait tous les jours.
        $this->assertTrue(StaffAccess::can($caissier, 'sell'));
        $this->assertTrue(StaffAccess::can($caissier, 'cart.remove'));
        $this->assertTrue(StaffAccess::can($caissier, 'catalog.view'));

        // Ce qui ne lui appartient pas : le catalogue est partagé par toutes
        // les caisses, supprimer chez l'un retire chez tous.
        $this->assertFalse(StaffAccess::can($caissier, 'catalog.edit'));
        $this->assertFalse(StaffAccess::can($caissier, 'catalog.delete'));
        $this->assertFalse(StaffAccess::can($caissier, 'staff.manage'));
        $this->assertFalse(StaffAccess::can($caissier, 'settings'));
        $this->assertFalse(StaffAccess::can($caissier, 'discount'));
    }

    public function test_a_cashier_sees_only_his_own_sales(): void
    {
        $this->assertSame('own', StaffAccess::salesScope(StaffAccess::ROLE_CASHIER));
        $this->assertFalse(StaffAccess::can(StaffAccess::ROLE_CASHIER, 'sales.till'));
        $this->assertFalse(StaffAccess::can(StaffAccess::ROLE_CASHIER, 'sales.all'));
    }

    public function test_only_the_owner_sees_every_till(): void
    {
        // C'est la demande explicite : « se patron an selman ki kapab wè tout
        // transactions ki pou kès 1 ni ki fèt sou kès 2 ».
        $this->assertSame('all',  StaffAccess::salesScope(StaffAccess::ROLE_OWNER));
        $this->assertSame('till', StaffAccess::salesScope(StaffAccess::ROLE_MANAGER));
        $this->assertSame('own',  StaffAccess::salesScope(StaffAccess::ROLE_CASHIER));
    }

    public function test_a_manager_runs_his_till_without_owning_the_business(): void
    {
        $gerant = StaffAccess::ROLE_MANAGER;

        $this->assertTrue(StaffAccess::can($gerant, 'sale.refund'));
        $this->assertTrue(StaffAccess::can($gerant, 'discount'));
        $this->assertTrue(StaffAccess::can($gerant, 'catalog.edit'));

        // Mais il ne supprime pas au catalogue et n'embauche personne.
        $this->assertFalse(StaffAccess::can($gerant, 'catalog.delete'));
        $this->assertFalse(StaffAccess::can($gerant, 'staff.manage'));
        $this->assertFalse(StaffAccess::can($gerant, 'sales.all'));
    }

    public function test_an_unknown_or_missing_role_can_do_nothing(): void
    {
        // Un employé mal enregistré doit rester à la porte, pas ouvrir la caisse.
        foreach ([null, '', 'admin', 'patron', 'CASHIER'] as $role) {
            $this->assertFalse(StaffAccess::can($role, 'sell'),
                'Un rôle inconnu ne doit rien pouvoir faire.');
            $this->assertSame('own', StaffAccess::salesScope($role));
        }
    }

    public function test_serious_actions_ask_for_the_code_again(): void
    {
        // Une caisse reste ouverte sur le comptoir : le code protège là.
        $this->assertTrue(StaffAccess::needsPin('sale.refund'));
        $this->assertTrue(StaffAccess::needsPin('discount'));
        $this->assertTrue(StaffAccess::needsPin('catalog.delete'));

        // Encaisser ne doit PAS redemander le code : en heure de pointe, ce
        // serait la garantie que le code finit écrit sur le comptoir.
        $this->assertFalse(StaffAccess::needsPin('sell'));
        $this->assertFalse(StaffAccess::needsPin('cart.remove'));
        $this->assertFalse(StaffAccess::needsPin('catalog.view'));
    }

    public function test_every_ability_granted_is_a_declared_one(): void
    {
        // Une faute de frappe dans la liste des droits donnerait un droit qui
        // n'est vérifié nulle part — donc un droit silencieusement absent.
        foreach (array_keys(StaffAccess::ROLES) as $role) {
            foreach (StaffAccess::abilitiesFor($role) as $ability) {
                $this->assertContains($ability, StaffAccess::ABILITIES,
                    "Le rôle $role accorde « $ability », inconnu de la liste.");
            }
        }
        foreach (StaffAccess::PIN_CONFIRMED as $ability) {
            $this->assertContains($ability, StaffAccess::ABILITIES);
        }
    }

    public function test_roles_go_from_the_most_open_to_the_least(): void
    {
        $patron   = count(StaffAccess::abilitiesFor(StaffAccess::ROLE_OWNER));
        $gerant   = count(StaffAccess::abilitiesFor(StaffAccess::ROLE_MANAGER));
        $caissier = count(StaffAccess::abilitiesFor(StaffAccess::ROLE_CASHIER));

        $this->assertGreaterThan($gerant, $patron);
        $this->assertGreaterThan($caissier, $gerant);
    }

    public function test_every_right_is_said_in_words_the_owner_understands(): void
    {
        // La fiche de chaque personne affiche ses droits. Un droit sans libellé
        // s'afficherait en clé technique (« catalog.delete ») sur l'écran du
        // patron.
        foreach (StaffAccess::ABILITIES as $ability) {
            $this->assertArrayHasKey($ability, StaffAccess::ABILITY_LABELS,
                "Le droit « $ability » n'a pas de libellé lisible.");
            $this->assertNotSame($ability, StaffAccess::label($ability));
        }
    }

    public function test_an_unknown_right_falls_back_to_its_key_rather_than_crashing(): void
    {
        $this->assertSame('inconnu.xyz', StaffAccess::label('inconnu.xyz'));
    }
}
