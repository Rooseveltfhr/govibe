<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Inventory\MovementType;
use PHPUnit\Framework\TestCase;

/**
 * Pourquoi le stock a bougé — logique pure.
 */
class MovementTypeTest extends TestCase
{
    public function test_a_loss_can_only_lower_the_stock(): void
    {
        // Une casse qui ferait MONTER le stock, c'est presque toujours un signe
        // moins oublié dans la saisie. Mieux vaut refuser que d'enregistrer un
        // mouvement qui raconte l'inverse de ce qui s'est passé.
        $this->assertTrue(MovementType::allowsDelta(MovementType::LOSS, -3));
        $this->assertFalse(MovementType::allowsDelta(MovementType::LOSS, 3));
    }

    public function test_a_delivery_can_only_raise_it(): void
    {
        $this->assertTrue(MovementType::allowsDelta(MovementType::PURCHASE, 24));
        $this->assertFalse(MovementType::allowsDelta(MovementType::PURCHASE, -24));
    }

    public function test_a_correction_goes_both_ways(): void
    {
        $this->assertTrue(MovementType::allowsDelta(MovementType::ADJUSTMENT, 5));
        $this->assertTrue(MovementType::allowsDelta(MovementType::ADJUSTMENT, -5));
    }

    public function test_a_movement_of_zero_says_nothing(): void
    {
        $this->assertFalse(MovementType::allowsDelta(MovementType::ADJUSTMENT, 0));
        $this->assertFalse(MovementType::allowsDelta(MovementType::COUNT, 0.0));
    }

    public function test_an_unknown_reason_is_refused(): void
    {
        $this->assertFalse(MovementType::isValid('disparu'));
        $this->assertFalse(MovementType::allowsDelta('disparu', -5));
    }

    public function test_the_owner_cannot_forge_a_sale_by_hand(): void
    {
        // Une vente est enregistrée par la caisse. La proposer dans le menu
        // déroulant permettrait de faire disparaître du stock sous couvert
        // d'une vente qui n'a encaissé aucun argent.
        $this->assertFalse(MovementType::isManual(MovementType::SALE));
        $this->assertNotContains(MovementType::SALE, MovementType::MANUAL);

        $this->assertTrue(MovementType::isManual(MovementType::LOSS));
        $this->assertTrue(MovementType::isManual(MovementType::PURCHASE));
    }

    public function test_only_a_count_replaces_the_stock(): void
    {
        $this->assertTrue(MovementType::replacesStock(MovementType::COUNT));
        $this->assertFalse(MovementType::replacesStock(MovementType::ADJUSTMENT));
        $this->assertFalse(MovementType::replacesStock(MovementType::SALE));
    }

    public function test_every_declared_type_is_complete(): void
    {
        foreach (MovementType::TYPES as $cle => $meta) {
            $this->assertNotEmpty($meta['label'], "Le motif « $cle » n'a pas de libellé.");
            $this->assertContains($meta['sens'], ['in', 'out', 'both'], "Sens invalide pour « $cle ».");
            $this->assertTrue(MovementType::isValid($cle));
        }

        foreach (MovementType::MANUAL as $cle) {
            $this->assertArrayHasKey($cle, MovementType::TYPES,
                "Le motif « $cle » est proposé au patron mais n'existe pas.");
        }
    }
}
