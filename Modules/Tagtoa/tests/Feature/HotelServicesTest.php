<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H3) — catalogue des services
|--------------------------------------------------------------------------
| Créer, modifier, activer/désactiver ; services standards rejouables ; room service lié à un
| Menu du MÊME hôtel ; réservé au type « hôtel » ; isolation entre hôtels ; jamais de suppression.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\DashboardModules;
use Modules\Tagtoa\App\Support\Hotel\HotelServiceRules;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelServicesTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $id = 'compte-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id]));
        Tenant::flush();
    }

    private function commerce(string $type = 'hotel', string $nom = 'Hôtel Belle Vue'): Business
    {
        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => $type, 'currency' => 'HTG']);
    }

    private function ajouter(array $extra = [])
    {
        return $this->post(route('tagtoa.hotel.services.store'), array_merge(['kind' => 'linen', 'name' => 'Serviettes'], $extra));
    }

    private function menu(string $tenant, string $nom = 'Carte du soir'): Menu
    {
        return Menu::allTenants()->create(['tenant_id' => $tenant, 'name' => $nom, 'alias' => 'm-'.uniqid(), 'currency' => 'HTG']);
    }

    public function test_a_hotel_can_create_edit_and_list_services(): void
    {
        $this->compte();
        $b = $this->commerce();

        $this->ajouter(['price' => '150', 'description' => 'Deux grandes serviettes', 'translations' => ['ht' => 'Sèvyèt', 'en' => 'Towels', 'xx' => 'ignoré']])
            ->assertSessionHasNoErrors();

        $s = HotelService::first();
        $this->assertSame($b->id, $s->tenant_id);
        $this->assertSame('linen', $s->kind);
        $this->assertSame('housekeeping', $s->team, 'Équipe par défaut de la nature.');
        $this->assertSame('150.00', $s->price);
        $this->assertSame(['ht' => 'Sèvyèt', 'en' => 'Towels'], $s->translations, 'Langues inconnues écartées.');
        $this->assertSame('Sèvyèt', $s->nameFor('ht'));
        $this->assertSame('Serviettes', $s->nameFor('es'), 'Repli sur le nom de base.');
        $this->assertTrue($s->is_active);

        $this->put(route('tagtoa.hotel.services.update', $s->id), ['kind' => 'reception', 'name' => 'Appeler la réception', 'price' => '', 'team' => ''])
            ->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('reception', $s->kind);
        $this->assertNull($s->price, 'Vide = gratuit.');
        $this->assertSame('reception', $s->team);
        $this->assertNull($s->translations);

        $this->get(route('tagtoa.hotel.services'))->assertOk()->assertSee('Appeler la réception')->assertSee('Gratuit');
    }

    public function test_room_service_requires_a_menu_of_the_same_hotel(): void
    {
        $this->compte();
        $b = $this->commerce();

        $this->ajouter(['kind' => 'room_service', 'name' => 'Room service'])->assertSessionHasErrors('menu_id');

        $autre = $this->menu('autre-hotel', 'Menu du voisin');
        $this->ajouter(['kind' => 'room_service', 'name' => 'Room service', 'menu_id' => $autre->id])->assertSessionHasErrors('menu_id');
        $this->assertSame(0, HotelService::count());

        $mien = $this->menu($b->id);
        $this->ajouter(['kind' => 'room_service', 'name' => 'Room service', 'menu_id' => $mien->id])->assertSessionHasNoErrors();
        $s = HotelService::first();
        $this->assertSame($mien->id, (int) $s->menu_id);
        $this->assertSame('kitchen', $s->team);

        $this->get(route('tagtoa.hotel.services'))->assertOk()->assertSee('Carte du soir')->assertDontSee('Menu du voisin');
    }

    public function test_a_menu_is_never_stored_on_a_service_that_is_not_room_service(): void
    {
        $this->compte();
        $b = $this->commerce();
        $mien = $this->menu($b->id);

        $this->ajouter(['kind' => 'housekeeping', 'name' => 'Ménage', 'menu_id' => $mien->id])->assertSessionHasNoErrors();
        $this->assertNull(HotelService::first()->menu_id);
    }

    /** @dataProvider invalidInputs */
    public function test_invalid_input_is_refused_and_nothing_is_created(array $input, string $key): void
    {
        $this->compte();
        $this->commerce();

        $this->ajouter($input)->assertSessionHasErrors($key);
        $this->assertSame(0, HotelService::count());
    }

    public static function invalidInputs(): array
    {
        return [
            'nature inconnue'   => [['kind' => 'casino'], 'kind'],
            'sans nom'          => [['name' => ''], 'name'],
            'nom trop long'     => [['name' => str_repeat('a', 81)], 'name'],
            'prix négatif'      => [['price' => '-1'], 'price'],
            'prix énorme'       => [['price' => '99999999'], 'price'],
            'équipe inconnue'   => [['team' => 'direction'], 'team'],
            'traduction longue' => [['translations' => ['ht' => str_repeat('b', 81)]], 'translations.ht'],
        ];
    }

    public function test_a_service_can_be_deactivated_and_reactivated_but_never_deleted(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter();
        $s = HotelService::first();

        $this->post(route('tagtoa.hotel.services.toggle', $s->id))->assertSessionHas('success');
        $this->assertFalse($s->fresh()->is_active);
        $this->post(route('tagtoa.hotel.services.toggle', $s->id));
        $this->assertTrue($s->fresh()->is_active);

        $this->delete('/tagtoa/hotel/services/'.$s->id)->assertStatus(405);
        $this->assertSame(1, HotelService::count());
    }

    public function test_default_services_are_added_once_and_never_overwrite(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter(['kind' => 'housekeeping', 'name' => 'Nettoyage de la chambre', 'price' => '300']);

        $this->post(route('tagtoa.hotel.services.defaults'))->assertSessionHas('success');
        $this->assertSame(count(HotelServiceRules::DEFAULTS), HotelService::count());
        $this->assertSame('300.00', HotelService::where('name', 'Nettoyage de la chambre')->first()->price, 'Existant gardé tel quel.');

        $this->post(route('tagtoa.hotel.services.defaults'));
        $this->assertSame(count(HotelServiceRules::DEFAULTS), HotelService::count(), 'Rejouable.');
    }

    public function test_the_catalog_is_capped_per_hotel(): void
    {
        $this->compte();
        $b = $this->commerce();
        for ($i = 0; $i < HotelServiceRules::MAX_PER_HOTEL; $i++) {
            HotelService::create(['tenant_id' => $b->id, 'kind' => 'information', 'name' => "S$i"]);
        }

        $this->ajouter()->assertSessionHas('error');
        $this->post(route('tagtoa.hotel.services.defaults'));
        $this->assertSame(HotelServiceRules::MAX_PER_HOTEL, HotelService::count());
    }

    /** @dataProvider otherTypes */
    public function test_only_hotels_can_manage_services(string $type): void
    {
        $this->compte();
        $this->commerce($type, 'Autre commerce');

        $this->get(route('tagtoa.hotel.services'))->assertRedirect(route('tagtoa.hub'))->assertSessionHas('error');
        $this->ajouter()->assertRedirect(route('tagtoa.hub'));
        $this->post(route('tagtoa.hotel.services.defaults'))->assertRedirect(route('tagtoa.hub'));

        $this->assertSame(0, DB::table('tagtoa_hotel_services')->count());
    }

    public static function otherTypes(): array
    {
        return ['restaurant' => ['restaurant'], 'quincaillerie' => ['quincaillerie'], 'bar' => ['bar']];
    }

    public function test_another_hotel_cannot_update_toggle_or_see_my_services(): void
    {
        $this->compte('compte-a');
        $this->commerce('hotel', 'Hôtel A');
        $this->ajouter(['name' => 'Massage secret']);
        $sA = HotelService::first();

        $this->compte('compte-b');
        $this->commerce('hotel', 'Hôtel B');

        $this->put(route('tagtoa.hotel.services.update', $sA->id), ['kind' => 'information', 'name' => 'Piraté'])->assertNotFound();
        $this->post(route('tagtoa.hotel.services.toggle', $sA->id))->assertNotFound();
        $this->get(route('tagtoa.hotel.services'))->assertOk()->assertDontSee('Massage secret');
        $this->post(route('tagtoa.hotel.services.defaults'));

        $fresh = DB::table('tagtoa_hotel_services')->where('id', $sA->id)->first();
        $this->assertSame('Massage secret', $fresh->name);
        $this->assertSame(1, (int) $fresh->is_active);
        $this->assertSame(1, DB::table('tagtoa_hotel_services')->where('tenant_id', $sA->tenant_id)->count(), 'Les standards de B ne vont jamais chez A.');
    }

    public function test_the_sidebar_lists_services_under_the_hotel_module(): void
    {
        $this->compte();
        $this->commerce();
        $urls = array_column(DashboardModules::enabled('module')['hotel']['children'], 'url');
        $this->assertContains('/tagtoa/hotel/services', $urls);
    }

    public function test_service_names_are_escaped_in_the_page(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter(['name' => '<script>alert(1)</script>']);

        $html = $this->get(route('tagtoa.hotel.services'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000184_create_tagtoa_hotel_services_table.php';
        $mig->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_services'));
        $mig->down();
        $this->assertFalse(Schema::hasTable('tagtoa_hotel_services'));
        $mig->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_services'));
    }
}
