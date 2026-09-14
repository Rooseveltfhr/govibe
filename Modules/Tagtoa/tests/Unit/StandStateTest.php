<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Stand\StandState;
use PHPUnit\Framework\TestCase;

/**
 * Deux axes d'état, jamais une chaîne unique.
 *
 * Les fondre produirait des états composés — « vendu-mais-non-réclamé »,
 * « actif-mais-perdu » — qui se multiplient sans fin.
 */
class StandStateTest extends TestCase
{
    public function test_sold_and_unclaimed_is_a_normal_situation(): void
    {
        // C'est l'état d'un objet qui dort dans un carton chez un revendeur.
        // Une chaîne unique devrait inventer un état pour le dire.
        $this->assertTrue(StandState::isClaimable(StandState::SOLD, StandState::UNCLAIMED));
    }

    public function test_an_active_stand_keeps_working_even_declared_lost(): void
    {
        // Le client attablé n'a pas à subir une déclaration administrative :
        // le menu continue de s'ouvrir.
        $this->assertTrue(StandState::resolvesToBusiness(StandState::ACTIVE));
        $this->assertFalse(StandState::isClaimable(StandState::LOST, StandState::ACTIVE));
    }

    public function test_a_lost_stand_can_no_longer_be_claimed(): void
    {
        // On ne peut pas empêcher qu'on vole un objet non gratté. On peut faire
        // qu'il ne serve à rien — c'est la seule protection réelle.
        foreach (StandState::PHYSICAL_CLOSED as $ferme) {
            $this->assertFalse(StandState::isClaimable($ferme, StandState::UNCLAIMED),
                "Un stand « $ferme » ne doit plus être réclamable.");
            $this->assertFalse(StandState::secretIsLive($ferme, StandState::UNCLAIMED));
        }
    }

    public function test_an_abandoned_activation_does_not_condemn_the_stand(): void
    {
        // Un client qui abandonne à mi-parcours doit pouvoir recommencer, sinon
        // son objet payé devient inutilisable pour une fenêtre expirée.
        $this->assertTrue(StandState::isClaimable(StandState::SOLD, StandState::CLAIM_PENDING));
    }

    public function test_an_already_claimed_stand_cannot_be_claimed_again(): void
    {
        $this->assertFalse(StandState::isClaimable(StandState::SOLD, StandState::ACTIVE));
        $this->assertFalse(StandState::isClaimable(StandState::SOLD, StandState::REVOKED));
    }

    public function test_a_transfer_in_progress_never_interrupts_the_menu(): void
    {
        // Le client attablé n'a pas à subir une négociation entre deux
        // propriétaires.
        $this->assertTrue(StandState::resolvesToBusiness(StandState::TRANSFER_PENDING));
    }

    public function test_a_suspended_stand_stops_resolving_to_the_business(): void
    {
        $this->assertFalse(StandState::resolvesToBusiness(StandState::SUSPENDED));
        $this->assertFalse(StandState::resolvesToBusiness(StandState::UNCLAIMED));
    }

    public function test_an_unknown_state_never_opens_anything(): void
    {
        // Se tromper vers « réclamable » donnerait un stand à qui n'y a pas
        // droit. Le repli va toujours vers le refus.
        $this->assertFalse(StandState::isClaimable('inventé', StandState::UNCLAIMED));
        $this->assertFalse(StandState::isClaimable(StandState::SOLD, 'inventé'));
        $this->assertFalse(StandState::isClaimable(null, null));
        $this->assertFalse(StandState::resolvesToBusiness('inventé'));
    }

    public function test_every_state_has_a_label(): void
    {
        foreach (StandState::PHYSICAL as $s) {
            $this->assertNotSame('Inconnu', StandState::physicalLabel($s), "« $s » n'a pas de libellé.");
        }
        foreach (StandState::DIGITAL as $s) {
            $this->assertNotSame('Inconnu', StandState::digitalLabel($s), "« $s » n'a pas de libellé.");
        }
    }

    public function test_the_two_axes_never_share_a_value(): void
    {
        // Une valeur commune aux deux axes rendrait une colonne interprétable
        // de deux façons — et c'est exactement la confusion qu'on évite.
        $this->assertSame([], array_intersect(StandState::PHYSICAL, StandState::DIGITAL));
    }
}
