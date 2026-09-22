<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — un nouveau menu part des informations du commerce
|--------------------------------------------------------------------------
| Le commerce (Business) porte déjà logo, type, adresse, téléphone et
| devise. La création d'un menu redemandait tout — la même information
| tapée deux fois, avec le risque réel qu'elle finisse par diverger entre
| les deux formulaires. Ce correctif PRÉ-REMPLIT depuis le commerce ; il ne
| retire AUCUN champ, et le marchand garde entièrement la main pour changer
| ce qui, pour ce menu précis, doit être différent (un hôtel dont le
| restaurant a son propre numéro, par exemple).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class MenuInheritsBusinessTest extends TestCase
{
    use RefreshDatabase;

    private function commerce(array $attrs = [])
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 'compte-1']));
        Tenant::flush();

        return app(BusinessService::class)->create('compte-1', array_merge([
            'name' => 'Bar Puya', 'type' => 'bar', 'address' => '2861 Somerset Drive',
            'phone' => '19548361449', 'currency' => 'USD', 'logo_path' => 'tagtoa/business-logos/puya.png',
        ], $attrs));
    }

    public function test_the_creation_form_shows_the_business_type_address_phone_and_currency(): void
    {
        $this->commerce();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('value="2861 Somerset Drive"', $html);
        $this->assertStringContainsString('value="19548361449"', $html);
        $this->assertMatchesRegularExpression('/<option value="bar" selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="USD"[^>]*selected>/', $html);
    }

    public function test_the_creation_form_previews_the_business_logo(): void
    {
        Storage::fake('public');
        $this->commerce();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString(Storage::url('tagtoa/business-logos/puya.png'), $html);
    }

    public function test_the_prefilled_values_are_only_a_default_the_merchant_can_still_change(): void
    {
        $this->commerce();

        $this->post(route('tagtoa.menu.dashboard.store'), [
            'name' => 'Petit-déjeuner', 'alias' => '', 'type' => 'restaurant', 'currency' => 'HTG',
            'phone' => '38112345', 'address' => 'Une autre adresse', 'translations_sent' => 1,
        ])->assertRedirect();

        $menu = Menu::firstOrFail();
        $this->assertSame('restaurant', $menu->type);
        $this->assertSame('38112345', $menu->phone);
        $this->assertSame('Une autre adresse', $menu->address);
    }

    public function test_a_new_menu_without_an_uploaded_logo_inherits_the_business_one(): void
    {
        $this->commerce();

        $this->post(route('tagtoa.menu.dashboard.store'), [
            'name' => 'Carte', 'alias' => '', 'currency' => 'USD', 'translations_sent' => 1,
        ])->assertRedirect();

        $menu = Menu::firstOrFail();
        $this->assertSame('tagtoa/business-logos/puya.png', $menu->logo_path);
    }

    public function test_uploading_a_logo_for_the_menu_overrides_the_business_default(): void
    {
        Storage::fake('public');
        $this->commerce();

        $this->post(route('tagtoa.menu.dashboard.store'), [
            'name' => 'Carte', 'alias' => '', 'currency' => 'USD', 'translations_sent' => 1,
            'logo' => UploadedFile::fake()->image('special.png'),
        ])->assertRedirect();

        $menu = Menu::firstOrFail();
        $this->assertNotSame('tagtoa/business-logos/puya.png', $menu->logo_path);
        $this->assertStringStartsWith('tagtoa/menu-logos/', $menu->logo_path);
    }

    public function test_editing_an_existing_menu_never_overwrites_its_own_logo_with_the_business_one(): void
    {
        $this->commerce();
        $menu = Menu::create([
            'tenant_id' => Tenant::id(), 'name' => 'Carte', 'alias' => 'carte-existante',
            'currency' => 'USD', 'logo_path' => 'tagtoa/menu-logos/deja-la.png', 'is_active' => true,
        ]);

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Carte', 'currency' => 'USD', 'translations_sent' => 1,
        ])->assertRedirect();

        $this->assertSame('tagtoa/menu-logos/deja-la.png', $menu->fresh()->logo_path);
    }
}
