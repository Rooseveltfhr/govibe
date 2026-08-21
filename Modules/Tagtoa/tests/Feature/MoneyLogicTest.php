<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — Tests Feature (money-critical)
|--------------------------------------------------------------------------
| Intégration DB réelle (SQLite mémoire via Orchestra Testbench — voir
| tests/TestCase.php). Tourne dans la CI de ce dépôt (job dédié, indépendant
| du host Biztap).
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\Tests\TestCase;

class MoneyLogicTest extends TestCase
{
    use RefreshDatabase;

    /** Une vente POS rejouée avec le même client_uuid ne crée qu'une seule vente. */
    public function test_pos_sale_is_idempotent(): void
    {
        $terminal = Terminal::create(['name' => 'Caisse test', 'currency' => 'HTG']);
        $svc = app(PosService::class);

        $payload = [
            'items'       => [['name' => 'Café', 'price' => 100, 'qty' => 2]],
            'payments'    => [['method' => 'cash', 'amount' => 200]],
            'client_uuid' => 'fixed-uuid-123',
        ];

        $a = $svc->recordSale($terminal, $payload);
        $b = $svc->recordSale($terminal, $payload); // rejoué (sync offline)

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $terminal->sales()->count());
        $this->assertEquals(200, $a->total);
    }

    /** Recharge puis débit : soldes et points cohérents, jamais négatifs. */
    public function test_loyalty_topup_and_redeem(): void
    {
        $program = Program::create([
            'name' => 'Fidélité', 'alias' => 'fidelite-test',
            'points_per_dollar' => 1, 'dollar_per_point' => 0.01, 'currency' => 'HTG',
        ]);
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($program, ['cardholder_name' => 'Jean'])['card'];

        $svc->topUp($card, 500);
        $card->refresh();
        $this->assertEquals(500, $card->balance);
        $this->assertEquals(500, $card->points);

        $svc->redeem($card, 120);
        $card->refresh();
        $this->assertEquals(380, $card->balance);

        $this->expectException(\RuntimeException::class);
        $svc->redeem($card, 9999); // solde insuffisant
    }
}
