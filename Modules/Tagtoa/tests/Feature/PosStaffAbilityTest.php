<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleReturn;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — StaffAccess::can() existait déjà (rôles, droits) mais n'était
| vérifié NULLE PART côté serveur : un caissier pouvait rembourser, modifier
| ou supprimer le catalogue en appelant directement la route, sans que rien
| ne s'y oppose — seul le formulaire le lui cachait à l'écran.
|--------------------------------------------------------------------------
*/
class PosStaffAbilityTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    /** Ouvre le poste avec un employé de ce rôle, code « 1234 ». */
    private function ouvrirAvec(Terminal $terminal, string $role): Staff
    {
        $staff = app(StaffService::class)->save($terminal->tenant_id, [
            'name' => 'Employé', 'pin' => '1234', 'role' => $role, 'terminal_id' => $terminal->id,
        ]);

        $this->post(route('tagtoa.pos.staff.login', $terminal->id), ['pin' => '1234'])
            ->assertRedirect();

        return $staff;
    }

    /* ------------------------------------------------------------------
       Un caissier ne touche jamais le catalogue.
       ------------------------------------------------------------------ */

    public function test_a_cashier_cannot_add_a_product(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $this->ouvrirAvec($caisse, StaffAccess::ROLE_CASHIER);

        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Intrus', 'price' => 10])
            ->assertForbidden();

        $this->assertSame(0, Product::where('name', 'Intrus')->count());
    }

    public function test_a_cashier_cannot_delete_a_product(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);
        $this->ouvrirAvec($caisse, StaffAccess::ROLE_CASHIER);

        $this->delete(route('tagtoa.pos.products.destroy', [$caisse->id, $article->id]))
            ->assertForbidden();

        $this->assertNotNull($article->fresh());
    }

    public function test_a_cashier_cannot_create_a_category(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $this->ouvrirAvec($caisse, StaffAccess::ROLE_CASHIER);

        $this->post(route('tagtoa.pos.categories.store'), ['name' => 'Rayon intrus'])
            ->assertForbidden();
    }

    public function test_a_cashier_cannot_refund_a_sale(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'stock' => 10, 'is_active' => true]);
        $sale = Sale::create([
            'terminal_id' => $caisse->id, 'reference' => 'V-1', 'subtotal' => 75, 'discount' => 0,
            'tax_total' => 0, 'total' => 75, 'currency' => 'HTG', 'payments' => [], 'sold_at' => now(), 'status' => 1,
        ]);
        $sale->items()->create(['product_id' => $article->id, 'name' => 'Coca', 'price' => 75, 'qty' => 1, 'line_total' => 75]);
        $this->ouvrirAvec($caisse, StaffAccess::ROLE_CASHIER);

        $this->post(route('tagtoa.pos.returns.store', $sale->id), [
            'qty' => [$article->id => 1], 'idempotency_key' => 'k-1',
        ])->assertForbidden();

        $this->assertSame(0, SaleReturn::count());
    }

    /* ------------------------------------------------------------------
       Un gérant a plus de droits qu'un caissier, mais pas tous.
       ------------------------------------------------------------------ */

    public function test_a_manager_can_edit_the_catalog_but_not_delete_from_it(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);
        $this->ouvrirAvec($caisse, StaffAccess::ROLE_MANAGER);

        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Fanta', 'price' => 70])
            ->assertRedirect();
        $this->assertSame(1, Product::where('name', 'Fanta')->count());

        $this->delete(route('tagtoa.pos.products.destroy', [$caisse->id, $article->id]))
            ->assertForbidden();
    }

    public function test_a_manager_can_refund_and_the_return_records_who_did_it(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'stock' => 10, 'is_active' => true]);
        $sale = Sale::create([
            'terminal_id' => $caisse->id, 'reference' => 'V-1', 'subtotal' => 75, 'discount' => 0,
            'tax_total' => 0, 'total' => 75, 'currency' => 'HTG', 'payments' => [], 'sold_at' => now(), 'status' => 1,
        ]);
        $sale->items()->create(['product_id' => $article->id, 'name' => 'Coca', 'price' => 75, 'qty' => 1, 'line_total' => 75]);
        $gerant = $this->ouvrirAvec($caisse, StaffAccess::ROLE_MANAGER);

        $this->post(route('tagtoa.pos.returns.store', $sale->id), [
            'qty' => [$article->id => 1], 'idempotency_key' => 'k-1',
        ])->assertRedirect();

        // Un mouvement d'argent laisse une trace nominative : c'était fixé à
        // null en dur avant, donc jamais renseigné même employé connecté.
        $this->assertSame($gerant->id, SaleReturn::first()->staff_id);
    }

    /* ------------------------------------------------------------------
       Aucun employé connecté = le patron travaille directement : rien ne change.
       ------------------------------------------------------------------ */

    public function test_the_owner_working_directly_without_any_staff_logged_in_is_unaffected(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        // Un employé EXISTE (donc `hasStaff` est vrai) mais personne n'a ouvert
        // de session sur CE poste : c'est le patron qui est aux commandes.
        app(StaffService::class)->save($caisse->tenant_id, [
            'name' => 'Employé', 'pin' => '1234', 'role' => StaffAccess::ROLE_CASHIER, 'terminal_id' => $caisse->id,
        ]);

        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Riz', 'price' => 120])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Product::where('name', 'Riz')->count());
    }
}
