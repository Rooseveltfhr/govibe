<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Catalog\Pricing;
use PHPUnit\Framework\TestCase;

/**
 * Prix d'achat, prix de vente, et ce qu'il reste entre les deux.
 *
 * Beaucoup de petits commerces savent combien ils ont vendu et pas du tout
 * combien ils ont gagné. C'est la différence entre une caisse qui compte
 * l'argent et un outil qui dit si le commerce tient debout.
 */
class PricingTest extends TestCase
{
    public function test_the_margin_is_what_is_left_on_one_unit(): void
    {
        $this->assertSame(15.0, Pricing::margin(60, 75));
        $this->assertSame(290.0, Pricing::margin(60, 350));
    }

    public function test_without_a_cost_price_the_margin_is_unknown_not_zero(): void
    {
        // Afficher « 0 » laisserait croire que le commerce ne gagne rien, alors
        // que la vérité est qu'on ne sait pas encore.
        $this->assertNull(Pricing::margin(null, 75));
        $this->assertNull(Pricing::marginPercent(null, 75));
        $this->assertNotSame(0.0, Pricing::margin(null, 75));
    }

    public function test_the_percentage_says_what_is_left_on_a_hundred_collected(): void
    {
        // Convention commerciale : pourcentage du prix de VENTE.
        $this->assertSame(20.0, Pricing::marginPercent(60, 75));
        $this->assertSame(50.0, Pricing::marginPercent(50, 100));
        $this->assertSame(34.0, Pricing::marginPercent(6600, 10000));
    }

    public function test_an_article_given_away_has_no_margin_to_show(): void
    {
        // Et surtout pas une division par zéro.
        $this->assertNull(Pricing::marginPercent(60, 0));
        $this->assertNull(Pricing::marginPercent(0, 0));
    }

    public function test_selling_at_a_loss_is_visible_rather_than_discovered_later(): void
    {
        // Écouler un stock qui approche de sa date arrive — mais cela doit se
        // voir tout de suite.
        $this->assertTrue(Pricing::isSoldAtLoss(80, 75));
        $this->assertFalse(Pricing::isSoldAtLoss(60, 75));
        $this->assertFalse(Pricing::isSoldAtLoss(75, 75));
        // Sans prix d'achat, on ne peut rien affirmer.
        $this->assertFalse(Pricing::isSoldAtLoss(null, 75));
    }

    public function test_a_suggested_price_reaches_the_margin_it_was_asked_for(): void
    {
        $prix = Pricing::suggestPrice(60, 20);

        $this->assertSame(75.0, $prix);
        // Et l'aller-retour se referme.
        $this->assertSame(20.0, Pricing::marginPercent(60, $prix));
    }

    public function test_an_impossible_margin_is_refused_rather_than_absurd(): void
    {
        // Viser 100 % exigerait un prix infini.
        $this->assertNull(Pricing::suggestPrice(60, 100));
        $this->assertNull(Pricing::suggestPrice(60, 150));
        $this->assertNull(Pricing::suggestPrice(null, 20));
        $this->assertNull(Pricing::suggestPrice(-5, 20));
    }

    /* ------------------------------------------------------------------
       Unités : le riz se vend à la mamit, le poulet à la livre.
       ------------------------------------------------------------------ */

    public function test_local_units_are_part_of_the_list(): void
    {
        foreach (['mamit', 'timamit', 'gode', 'lb'] as $unite) {
            $this->assertArrayHasKey($unite, Pricing::UNITS, "L'unité « $unite » doit exister.");
        }
        $this->assertSame('Mamit', Pricing::unitLabel('mamit'));
    }

    public function test_an_unknown_unit_falls_back_to_the_piece(): void
    {
        $this->assertSame('piece', Pricing::unit('galon-brouette'));
        $this->assertSame('piece', Pricing::unit(null));
        $this->assertSame('Pièce', Pricing::unitLabel('inconnue'));
    }

    public function test_a_piece_is_never_sold_by_halves(): void
    {
        // Le client ne peut pas emporter une demi-bouteille : on arrondit vers
        // le haut plutôt que d'encaisser une quantité impossible.
        $this->assertSame(3.0, Pricing::normalizeQty('piece', 2.5));
        $this->assertSame(1.0, Pricing::normalizeQty('piece', 0.2));
        $this->assertSame(0.0, Pricing::normalizeQty('piece', 0));
    }

    public function test_rice_and_chicken_are_sold_by_fractions(): void
    {
        $this->assertSame(2.5, Pricing::normalizeQty('lb', 2.5));
        $this->assertSame(1.75, Pricing::normalizeQty('mamit', 1.75));
        $this->assertSame(0.5, Pricing::normalizeQty('kg', 0.5));
    }

    public function test_a_negative_quantity_is_never_sold(): void
    {
        $this->assertSame(0.0, Pricing::normalizeQty('lb', -3));
        $this->assertSame(0.0, Pricing::normalizeQty('piece', -1));
    }

    public function test_a_line_total_follows_the_unit(): void
    {
        // 2,5 livres de poulet à 120 la livre.
        $this->assertSame(300.0, Pricing::lineTotal('lb', 120, 2.5));
        // Mais 2,5 bouteilles font 3 bouteilles.
        $this->assertSame(225.0, Pricing::lineTotal('piece', 75, 2.5));
    }

    public function test_every_declared_unit_is_usable(): void
    {
        foreach (Pricing::UNITS as $cle => $meta) {
            $this->assertNotEmpty($meta['label'], "L'unité « $cle » n'a pas de libellé.");
            $this->assertIsBool($meta['decimal']);
            $this->assertSame($cle, Pricing::unit($cle));
        }
    }
}
