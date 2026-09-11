<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosSales;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Qui voit quelles ventes.
 *
 * Le commerce de Roosevelt : Jacqueline sur la caisse 1, Pierre sur la caisse 2.
 * Chacun répond de SES ventes ; seul le patron voit l'ensemble et sait qui
 * tenait quelle caisse.
 */
class PosSalesVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Terminal $caisse1;
    private Terminal $caisse2;
    private Staff $jacqueline;
    private Staff $pierre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caisse1 = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse 1', 'currency' => 'HTG', 'is_active' => true]);
        $this->caisse2 = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse 2', 'currency' => 'HTG', 'is_active' => true]);

        $this->jacqueline = app(StaffService::class)->save('t-1', [
            'name' => 'Jacqueline', 'pin' => '4821',
            'role' => StaffAccess::ROLE_CASHIER, 'terminal_id' => $this->caisse1->id,
        ]);
        $this->pierre = app(StaffService::class)->save('t-1', [
            'name' => 'Pierre', 'pin' => '9002',
            'role' => StaffAccess::ROLE_CASHIER, 'terminal_id' => $this->caisse2->id,
        ]);

        $coca = app(PosCatalog::class)->save($this->caisse1, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);

        // Chacun encaisse sur sa caisse.
        app(PosService::class)->recordSale($this->caisse1, ['items' => [['product_id' => $coca->id, 'qty' => 2]]], $this->jacqueline);
        app(PosService::class)->recordSale($this->caisse1, ['items' => [['product_id' => $coca->id, 'qty' => 1]]], $this->jacqueline);
        app(PosService::class)->recordSale($this->caisse2, ['items' => [['product_id' => $coca->id, 'qty' => 4]]], $this->pierre);
    }

    public function test_a_sale_records_who_cashed_it(): void
    {
        $vente = Sale::where('terminal_id', $this->caisse2->id)->first();

        $this->assertSame($this->pierre->id, $vente->staff_id);
        $this->assertSame('Pierre', $vente->cashier_name);
    }

    public function test_a_cashier_sees_only_her_own_sales(): void
    {
        $vues = app(PosSales::class)->visibleTo($this->jacqueline, $this->caisse1)->get();

        $this->assertCount(2, $vues);
        $this->assertSame([$this->jacqueline->id, $this->jacqueline->id], $vues->pluck('staff_id')->all());
    }

    public function test_a_cashier_never_sees_her_colleagues_sales(): void
    {
        // Même en demandant la caisse du collègue : la portée vient du RÔLE,
        // pas de ce que l'écran réclame.
        $vues = app(PosSales::class)->visibleTo($this->jacqueline, $this->caisse2)->get();

        $this->assertCount(2, $vues);
        $this->assertNotContains($this->pierre->id, $vues->pluck('staff_id')->all());
    }

    public function test_the_owner_sees_every_till_and_who_was_on_it(): void
    {
        // La demande exacte : « se patron an selman ki ka wè tout transactions
        // ki pou kès 1 ni ki fèt sou kès 2 ».
        $toutes = app(PosSales::class)->forOwner('t-1')->with('staff')->get();

        $this->assertCount(3, $toutes);
        $this->assertEqualsCanonicalizing(
            ['Jacqueline', 'Jacqueline', 'Pierre'],
            $toutes->map->cashier_name->all()
        );
        $this->assertEqualsCanonicalizing(
            [$this->caisse1->id, $this->caisse1->id, $this->caisse2->id],
            $toutes->pluck('terminal_id')->all()
        );
    }

    public function test_a_manager_sees_his_till_whoever_stood_at_it(): void
    {
        // Le gérant répond de sa caisse, y compris des ventes d'un collègue qui
        // l'a relayé dans la journée.
        $gerant = app(StaffService::class)->save('t-1', [
            'name' => 'Marie', 'pin' => '5555',
            'role' => StaffAccess::ROLE_MANAGER, 'terminal_id' => $this->caisse1->id,
        ]);

        $vues = app(PosSales::class)->visibleTo($gerant, $this->caisse1)->get();

        $this->assertCount(2, $vues);
        $this->assertSame(['Jacqueline', 'Jacqueline'], $vues->map->cashier_name->all());
    }

    public function test_no_one_ever_sees_a_neighbouring_shops_takings(): void
    {
        $chezVoisin = Terminal::create(['tenant_id' => 't-2', 'name' => 'Bar', 'currency' => 'HTG', 'is_active' => true]);
        $biere = app(PosCatalog::class)->save($chezVoisin, ['name' => 'Bière', 'price' => 100, 'is_active' => true]);
        app(PosService::class)->recordSale($chezVoisin, ['items' => [['product_id' => $biere->id, 'qty' => 1]]]);

        // Même la portée la plus ouverte reste « toutes MES caisses ».
        $this->assertCount(3, app(PosSales::class)->forOwner('t-1')->get());
        $this->assertCount(1, app(PosSales::class)->forOwner('t-2')->get());
    }

    public function test_takings_per_cashier_are_ranked_by_amount(): void
    {
        $parCaissier = app(PosSales::class)->byCashier(app(PosSales::class)->forOwner('t-1'));

        // Pierre : 4 × 75 = 300 · Jacqueline : (2+1) × 75 = 225.
        $this->assertSame(['Pierre', 'Jacqueline'], array_keys($parCaissier));
        $this->assertSame(1, $parCaissier['Pierre']['count']);
        $this->assertEquals(300.0, $parCaissier['Pierre']['total']);
        $this->assertSame(2, $parCaissier['Jacqueline']['count']);
        $this->assertEquals(225.0, $parCaissier['Jacqueline']['total']);
    }

    public function test_a_shop_without_employees_still_sells_as_before(): void
    {
        // Rien ne casse pour un commerce qui n'a créé personne : la vente est
        // celle du patron.
        $seul = Terminal::create(['tenant_id' => 't-9', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
        $p = app(PosCatalog::class)->save($seul, ['name' => 'Pain', 'price' => 50, 'is_active' => true]);

        $vente = app(PosService::class)->recordSale($seul, ['items' => [['product_id' => $p->id, 'qty' => 1]]]);

        $this->assertNull($vente->staff_id);
        $this->assertSame('Patron', $vente->cashier_name);
    }

    public function test_a_departing_employee_never_takes_the_takings_with_her(): void
    {
        // Une recette appartient au commerce, pas à la personne qui l'a
        // encaissée : supprimer Jacqueline ne doit effacer aucune vente.
        $avant = Sale::count();

        $this->jacqueline->delete();

        $this->assertSame($avant, Sale::count(), 'Les ventes doivent survivre au départ de l\'employée.');
        $this->assertSame(3, app(PosSales::class)->forOwner('t-1')->count());
        // Elles reviennent au patron plutôt que de pointer dans le vide.
        $this->assertSame('Patron', Sale::where('terminal_id', $this->caisse1->id)->first()->cashier_name);
    }
}
