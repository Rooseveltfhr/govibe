<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — les droits « encaisser » et « remise » sont vérifiés (CS-14, POS-8)
|--------------------------------------------------------------------------
| Les droits existaient (StaffAccess) mais sale() et sync() ne les vérifiaient
| pas : un caissier pouvait accorder n'importe quelle remise en appelant la
| route. Sans employé connecté (le patron) rien ne change.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\Tests\TestCase;

class PosSellDiscountAbilityTest extends TestCase
{
    use RefreshDatabase;

    private Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        $this->terminal = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
    }

    private function ouvrirAvec(string $role, string $pin = '1234'): Staff
    {
        $staff = app(StaffService::class)->save('t-1', ['name' => 'Employé '.$role, 'pin' => $pin, 'role' => $role, 'terminal_id' => $this->terminal->id]);
        $this->post(route('tagtoa.pos.staff.login', $this->terminal->id), ['pin' => $pin])->assertRedirect();

        return $staff;
    }

    private function vente(array $extra = []): array
    {
        return array_merge(['items' => [['name' => 'Divers', 'price' => 1000, 'qty' => 1]]], $extra);
    }

    private function sale(array $extra = [])
    {
        return $this->postJson(route('tagtoa.pos.sale', $this->terminal->id), $this->vente($extra));
    }

    private function sync(array $sales)
    {
        return $this->postJson(route('tagtoa.pos.sync', $this->terminal->id), ['sales' => $sales]);
    }

    /* ---------- en ligne ---------- */

    public function test_a_cashier_can_sell_without_a_discount(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_CASHIER);
        $this->sale()->assertOk();
        $this->assertSame(1, Sale::count());
    }

    public function test_a_cashier_cannot_grant_a_discount_and_nothing_is_recorded(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_CASHIER);

        $this->sale(['discount' => 400])->assertForbidden();

        $this->assertSame(0, Sale::count());
    }

    public function test_a_cashier_may_send_a_zero_discount(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_CASHIER);
        $this->sale(['discount' => 0])->assertOk();
    }

    public function test_a_manager_can_grant_a_discount(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_MANAGER);

        $this->sale(['discount' => 400])->assertOk();

        $this->assertSame(600.0, (float) Sale::first()->total);
    }

    public function test_a_courier_cannot_sell_at_all(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_COURIER);

        $this->sale()->assertForbidden();

        $this->assertSame(0, Sale::count());
    }

    public function test_the_owner_without_a_staff_session_is_unaffected(): void
    {
        app(StaffService::class)->save('t-1', ['name' => 'Employé', 'pin' => '1234', 'role' => StaffAccess::ROLE_CASHIER, 'terminal_id' => $this->terminal->id]);

        $this->sale(['discount' => 400])->assertOk();
        $this->assertSame(1, Sale::count());
    }

    /* ---------- reprise hors ligne ---------- */

    public function test_an_offline_replay_with_a_discount_is_refused_for_a_cashier_and_stays_on_the_till(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_CASHIER);

        $res = $this->sync([
            array_merge($this->vente(['discount' => 400]), ['client_uuid' => 'D1']),
            array_merge($this->vente(), ['client_uuid' => 'N1']),
        ])->assertOk()->json('results');

        $this->assertFalse($res[0]['ok']);
        $this->assertSame('forbidden', $res[0]['code']);
        $this->assertNotEmpty($res[0]['message']);
        $this->assertTrue($res[1]['ok'], 'La vente sans remise du même lot passe.');
        $this->assertSame(1, Sale::count());
        $this->assertSame('N1', Sale::first()->client_uuid);
    }

    public function test_a_manager_can_replay_the_refused_discount_sale_afterwards(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_CASHIER);
        $vente = array_merge($this->vente(['discount' => 400]), ['client_uuid' => 'D1']);
        $this->sync([$vente])->assertOk();
        $this->assertSame(0, Sale::count());

        $this->post(route('tagtoa.pos.staff.logout', $this->terminal->id));
        $this->ouvrirAvec(StaffAccess::ROLE_MANAGER, '5678');

        $res = $this->sync([$vente])->assertOk()->json('results.0');
        $this->assertTrue($res['ok']);
        $this->assertSame(1, Sale::count());
    }

    public function test_a_courier_replay_is_refused_for_every_sale(): void
    {
        $this->ouvrirAvec(StaffAccess::ROLE_COURIER);

        $res = $this->sync([array_merge($this->vente(), ['client_uuid' => 'C1'])])->assertOk()->json('results.0');

        $this->assertFalse($res['ok']);
        $this->assertSame('forbidden', $res['code']);
        $this->assertSame(0, Sale::count());
    }

    public function test_offline_replay_without_any_staff_session_is_unaffected(): void
    {
        $res = $this->sync([array_merge($this->vente(['discount' => 400]), ['client_uuid' => 'O1'])])->assertOk()->json('results.0');

        $this->assertTrue($res['ok']);
    }
}
