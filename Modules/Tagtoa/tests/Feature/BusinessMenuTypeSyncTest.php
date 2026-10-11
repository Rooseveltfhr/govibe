<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — Business.type et Menu.type ne divergent plus
|--------------------------------------------------------------------------
| Signalé (analyse de clarté) : deux colonnes séparées pour le même
| « métier » — changer l'une (Paramètres POS, ou le <select> de l'assistant
| Menu) ne touchait jamais l'autre. Un marchand pouvait finir avec deux
| métiers différents pour le même commerce, chacun donnant de mauvaises
| suggestions de catégories/champs à l'écran qui l'ignore.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class BusinessMenuTypeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    public function test_changing_the_business_type_cascades_to_its_menu(): void
    {
        $this->patron();
        $business = app(BusinessService::class)->create('t-1', ['name' => 'Lakay', 'type' => 'restaurant', 'currency' => 'HTG']);
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lakay', 'alias' => 'lakay-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG']);

        app(BusinessService::class)->update($business, [
            'name' => 'Lakay', 'type' => 'salon', 'currency' => 'HTG',
            'sells_products' => true, 'sells_services' => false,
        ]);

        $this->assertSame('salon', $menu->fresh()->type);
    }

    public function test_changing_the_business_type_via_the_pos_settings_shortcut_also_cascades(): void
    {
        $this->patron();
        app(BusinessService::class)->create('t-1', ['name' => 'Lakay', 'type' => 'bar', 'currency' => 'HTG']);
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lakay', 'alias' => 'lakay-'.uniqid(), 'type' => 'bar', 'currency' => 'HTG']);

        $this->put(route('tagtoa.pos.settings.business-type'), ['type' => 'quincaillerie'])->assertRedirect();

        $this->assertSame('quincaillerie', $menu->fresh()->type);
    }

    public function test_changing_the_menu_type_in_the_wizard_cascades_to_the_business(): void
    {
        $this->patron();
        $business = app(BusinessService::class)->create('t-1', ['name' => 'Lakay', 'type' => 'restaurant', 'currency' => 'HTG']);
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lakay', 'alias' => 'lakay-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG']);

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lakay', 'type' => 'hotel', 'currency' => 'HTG', 'translations_sent' => 1,
        ])->assertRedirect();

        $this->assertSame('hotel', $business->fresh()->type);
    }

    public function test_changing_the_business_type_without_a_menu_yet_does_not_error(): void
    {
        $this->patron();
        $business = app(BusinessService::class)->create('t-1', ['name' => 'Lakay', 'type' => 'restaurant', 'currency' => 'HTG']);

        app(BusinessService::class)->update($business, [
            'name' => 'Lakay', 'type' => 'bar', 'currency' => 'HTG',
            'sells_products' => true, 'sells_services' => false,
        ]);

        $this->assertSame('bar', $business->fresh()->type);
        $this->assertSame(0, Menu::count());
    }

    public function test_changing_the_menu_type_without_a_business_declared_does_not_error(): void
    {
        $this->patron();
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lakay', 'alias' => 'lakay-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG']);

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lakay', 'type' => 'bar', 'currency' => 'HTG', 'translations_sent' => 1,
        ])->assertRedirect();

        $this->assertSame('bar', $menu->fresh()->type);
    }

    public function test_updating_the_business_without_changing_its_type_does_not_touch_the_menu(): void
    {
        $this->patron();
        $business = app(BusinessService::class)->create('t-1', ['name' => 'Lakay', 'type' => 'restaurant', 'currency' => 'HTG']);
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lakay', 'alias' => 'lakay-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG']);

        app(BusinessService::class)->update($business, [
            'name' => 'Lakay Grill', 'type' => 'restaurant', 'currency' => 'HTG',
            'sells_products' => true, 'sells_services' => false,
        ]);

        $this->assertSame('restaurant', $menu->fresh()->type);
    }
}
