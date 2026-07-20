<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Store\Cart;
use PHPUnit\Framework\TestCase;

/**
 * Panier boutique (logique pure : prix serveur, quantités, sous-total).
 */
class CartTest extends TestCase
{
    private function catalog(): array
    {
        return [
            1 => ['price' => 800.0, 'name' => 'T-shirt'],
            2 => ['price' => 300.5, 'name' => 'Bracelet'],
        ];
    }

    public function test_price_comes_from_catalog_not_client(): void
    {
        // Le client envoie un prix bidon → ignoré : on prend le prix serveur.
        $res = Cart::build($this->catalog(), [['id' => 1, 'qty' => 2, 'price' => 1]]);

        $this->assertCount(1, $res['lines']);
        $this->assertSame(800.0, $res['lines'][0]['price']);
        $this->assertSame(1600.0, $res['lines'][0]['line_total']);
        $this->assertSame(1600.0, $res['subtotal']);
    }

    public function test_unknown_products_ignored(): void
    {
        $res = Cart::build($this->catalog(), [['id' => 999, 'qty' => 5], ['id' => 2, 'qty' => 2]]);

        $this->assertCount(1, $res['lines']);
        $this->assertSame(601.0, $res['subtotal']); // 300.5 * 2
    }

    public function test_quantity_clamped(): void
    {
        $this->assertSame(1, Cart::clampQty(1));
        $this->assertSame(99, Cart::clampQty(500));
        $this->assertSame(0, Cart::clampQty(0));
        $this->assertSame(0, Cart::clampQty(-3));
    }

    public function test_zero_or_negative_qty_dropped(): void
    {
        $res = Cart::build($this->catalog(), [['id' => 1, 'qty' => 0], ['id' => 2, 'qty' => -1]]);

        $this->assertSame([], $res['lines']);
        $this->assertSame(0.0, $res['subtotal']);
    }

    public function test_subtotal_multiple_lines(): void
    {
        $res = Cart::build($this->catalog(), [['id' => 1, 'qty' => 1], ['id' => 2, 'qty' => 2]]);

        $this->assertCount(2, $res['lines']);
        $this->assertSame(1401.0, $res['subtotal']); // 800 + 601
    }
}
