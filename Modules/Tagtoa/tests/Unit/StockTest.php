<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Inventory\StockService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure de stock : null = illimité, quantités décimales.
 */
class StockTest extends TestCase
{
    public function test_unlimited_when_null(): void
    {
        $this->assertTrue(StockService::canFulfill(null, 999));
        $this->assertNull(StockService::remaining(null, 5));
        $this->assertFalse(StockService::isLow(null));
        $this->assertFalse(StockService::isOut(null));
    }

    public function test_can_fulfill(): void
    {
        $this->assertTrue(StockService::canFulfill(5, 5));
        $this->assertTrue(StockService::canFulfill(5, 3));
        $this->assertFalse(StockService::canFulfill(2, 3));
        $this->assertFalse(StockService::canFulfill(0, 1));
    }

    public function test_remaining_never_negative(): void
    {
        $this->assertSame(2.0, StockService::remaining(5, 3));
        $this->assertSame(0.0, StockService::remaining(3, 3));
        $this->assertSame(0.0, StockService::remaining(3, 10));
    }

    public function test_is_out(): void
    {
        $this->assertTrue(StockService::isOut(0));
        $this->assertFalse(StockService::isOut(1));
    }

    /* ------------------------------------------------------------------
       Quantités décimales — le riz à la mamit, la viande à la livre.
       ------------------------------------------------------------------ */

    public function test_half_a_pound_is_a_real_quantity(): void
    {
        // Le cœur du commerce de quartier : on vend 2,5 livres, pas 2.
        $this->assertTrue(StockService::canFulfill(3.0, 2.5));
        $this->assertFalse(StockService::canFulfill(2.0, 2.5));
        $this->assertSame(0.5, StockService::remaining(3.0, 2.5));
    }

    public function test_a_quantity_is_never_negative(): void
    {
        // Une ligne de vente négative n'existe pas : elle rendrait de l'argent.
        $this->assertSame(0.0, StockService::qty(-4));
        $this->assertSame(2.5, StockService::qty(2.5));
    }

    public function test_quantities_stop_at_three_decimals(): void
    {
        // Aligné sur decimal(12,3) : ce que PHP calcule est ce que la base garde.
        $this->assertSame(2.457, StockService::qty(2.4567));
        $this->assertSame(0.333, StockService::qty(1 / 3));
    }

    public function test_an_almost_empty_stock_is_empty(): void
    {
        // 0,0004 mamit de riz n'est pas du riz : c'est une poussière d'arrondi.
        $this->assertTrue(StockService::isOut(0.0004));
        $this->assertFalse(StockService::isOut(0.01));
    }

    /* ------------------------------------------------------------------
       Seuil de réapprovisionnement — UNE seule règle dans tout TAGTOA.
       ------------------------------------------------------------------ */

    public function test_a_shop_that_set_nothing_is_still_warned(): void
    {
        // Mieux vaut un plancher commun que jamais d'alerte.
        $this->assertTrue(StockService::isLow(3, null));
        $this->assertFalse(StockService::isLow(20, null));
    }

    public function test_the_shop_may_set_its_own_threshold(): void
    {
        // Une boulangerie qui vend 200 pains par jour n'alerte pas à 5.
        $this->assertTrue(StockService::isLow(40, 50));
        $this->assertFalse(StockService::isLow(60, 50));
    }

    public function test_an_untracked_stock_never_raises_an_alert(): void
    {
        // Un service, un plat préparé à la commande : rien à compter.
        $this->assertFalse(StockService::isLow(null, 5));
        $this->assertFalse(StockService::isLow(null, null));
    }

    public function test_a_threshold_may_itself_be_fractional(): void
    {
        // « Recommande quand il reste moins d'une demi-mamit. »
        $this->assertTrue(StockService::isLow(0.4, 0.5));
        $this->assertFalse(StockService::isLow(0.6, 0.5));
    }

    public function test_a_zero_threshold_still_warns_at_zero(): void
    {
        // Régler 0 veut dire « préviens-moi à la rupture », pas « jamais ».
        $this->assertTrue(StockService::isLow(0, 0));
        $this->assertFalse(StockService::isLow(1, 0));
    }

    public function test_a_negative_threshold_cannot_silence_the_alert(): void
    {
        // Une saisie aberrante ne doit pas éteindre l'alerte de rupture.
        $this->assertTrue(StockService::isLow(0, -10));
    }
}
