<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

/**
 * L'isolation entre commerces est une propriété du MODÈLE, plus une consigne.
 *
 * Ces tests ouvrent une vraie session marchande — sans quoi la portée
 * automatique ne s'active pas et l'on testerait pour de mauvaises raisons.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** Ouvre une session pour ce commerce (c'est ce que lit Tenant::id()). */
    private function actingAsMerchant(string $tenantId): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId]));
    }

    private function seedTwoMerchants(): void
    {
        // Créées hors session : chacune porte son commerce explicitement.
        Menu::create(['tenant_id' => 't-1', 'name' => 'Boulangerie', 'alias' => 'boulangerie', 'currency' => 'HTG']);
        Menu::create(['tenant_id' => 't-2', 'name' => 'Bar Lakay',   'alias' => 'bar-lakay',   'currency' => 'HTG']);

        PaymentPage::create(['tenant_id' => 't-1', 'title' => 'Facture A', 'alias' => 'fa', 'default_currency' => 'HTG']);
        PaymentPage::create(['tenant_id' => 't-2', 'title' => 'Facture B', 'alias' => 'fb', 'default_currency' => 'HTG']);

        Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse A', 'currency' => 'HTG']);
        Terminal::create(['tenant_id' => 't-2', 'name' => 'Caisse B', 'currency' => 'HTG']);
    }

    public function test_a_query_that_forgot_its_filter_no_longer_leaks(): void
    {
        $this->seedTwoMerchants();
        $this->actingAsMerchant('t-1');

        // Aucun where('tenant_id') ici : c'est exactement la faute que le trait
        // doit rendre impossible.
        $this->assertSame(['Boulangerie'], Menu::pluck('name')->all());
        $this->assertSame(['Facture A'], PaymentPage::pluck('title')->all());
        $this->assertSame(['Caisse A'], Terminal::pluck('name')->all());
    }

    public function test_the_neighbour_sees_only_his_own(): void
    {
        $this->seedTwoMerchants();
        $this->actingAsMerchant('t-2');

        $this->assertSame(['Bar Lakay'], Menu::pluck('name')->all());
        $this->assertSame(1, Menu::count());
    }

    public function test_fetching_a_neighbours_record_by_its_id_returns_nothing(): void
    {
        $this->seedTwoMerchants();
        $chezVoisin = Menu::where('alias', 'bar-lakay')->first();

        $this->actingAsMerchant('t-1');

        $this->assertNull(Menu::find($chezVoisin->id), 'Un identifiant deviné ne doit rien donner.');
        $this->assertSame(0, Menu::where('alias', 'bar-lakay')->count());
    }

    public function test_a_new_record_receives_its_merchant_without_being_told(): void
    {
        $this->actingAsMerchant('t-1');

        $menu = Menu::create(['name' => 'Nouveau', 'alias' => 'nouveau', 'currency' => 'HTG']);

        $this->assertSame('t-1', $menu->tenant_id);
    }

    public function test_a_deliberate_merchant_is_never_overwritten_by_the_session(): void
    {
        // Cas réel : une commission encaissée par webhook connaît son commerce
        // mieux que la session en cours.
        $this->actingAsMerchant('t-1');

        $menu = Menu::create(['tenant_id' => 't-2', 'name' => 'Pour le voisin', 'alias' => 'voisin', 'currency' => 'HTG']);

        $this->assertSame('t-2', $menu->tenant_id);
    }

    public function test_a_public_visitor_can_still_open_a_public_page(): void
    {
        // Client anonyme qui scanne un QR : aucune session, donc aucune portée.
        // Sans cela, toutes les pages publiques renverraient 404.
        $this->seedTwoMerchants();

        $this->assertNull(Tenant::id());
        $this->assertNotNull(Menu::where('alias', 'bar-lakay')->first());
        $this->assertSame(2, Menu::count());
    }

    public function test_the_founder_can_still_see_every_merchant_when_he_asks_for_it(): void
    {
        // Sortie explicite : la vue plateforme du fondateur.
        $this->seedTwoMerchants();
        $this->actingAsMerchant('t-1');

        $this->assertSame(1, Menu::count());
        $this->assertSame(2, Menu::allTenants()->count());
    }

    public function test_the_scope_survives_a_join_between_two_tenant_tables(): void
    {
        // Deux colonnes tenant_id en présence : sans préfixe de table, SQL
        // échouerait sur une colonne ambiguë.
        $this->seedTwoMerchants();
        $this->actingAsMerchant('t-1');

        $rows = Menu::query()
            ->join('tagtoa_payment_pages', 'tagtoa_payment_pages.tenant_id', '=', 'tagtoa_menus.tenant_id')
            ->pluck('tagtoa_menus.name');

        $this->assertSame(['Boulangerie'], $rows->all());
    }
}
