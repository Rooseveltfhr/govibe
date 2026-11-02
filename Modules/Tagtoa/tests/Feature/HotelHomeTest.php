<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL — l'entrée « Mon Hôtel » de la barre latérale et son démarrage pas à pas
|--------------------------------------------------------------------------
| L'hôtel a sa PROPRE place dans la barre latérale (pas seulement dans « Mes commerces »). Pour un hôtel : les étapes
| de mise en place (profil, chambres, tarifs, menu, services, QR, personnel). Sinon : ouvrir l'hôtel du compte, ou
| « Créer mon hôtel » (métier déjà choisi).
|
| Et sur téléphone, les tableaux de chambres et de services deviennent des cartes (les boutons ne se chevauchent plus).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRatePlan;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelRoomType;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\DashboardModules;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelHomeTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $id = 'c-a'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id, 'name' => 'Rose']));
        Tenant::flush();
    }

    private function commerce(string $type, string $nom): Business
    {
        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => $type, 'currency' => 'HTG']);
    }

    /* ---------- barre latérale ---------- */

    public function test_the_sidebar_locates_the_start_page_and_the_room_screens_inside_the_hotel_module(): void
    {
        $this->compte();
        $this->commerce('hotel', 'Belle Vue');

        $this->assertSame('hotel', DashboardModules::locate('/tagtoa/hotel')[0]);
        $this->assertSame('/tagtoa/hotel', DashboardModules::locate('/tagtoa/hotel')[1]);
        $this->assertSame('/tagtoa/hotel/rooms', DashboardModules::locate('/tagtoa/hotel/rooms')[1]);
        $this->assertSame('/tagtoa/hotel/room-types', DashboardModules::locate('/tagtoa/hotel/room-types')[1]);
    }

    public function test_the_dashboard_home_lists_the_hotel_entry_for_a_restaurant_too(): void
    {
        $this->compte();
        $this->commerce('restaurant', 'Chez Rose');

        $this->get(route('tagtoa.hub'))->assertOk()->assertSee('Mon Hôtel');
    }

    /* ---------- accueil de l'hôtel : étapes ---------- */

    public function test_a_new_hotel_shows_its_steps_with_zero_progress_and_the_restaurant_step_is_optional(): void
    {
        $this->compte();
        $this->commerce('hotel', 'Belle Vue');

        $r = $this->get(route('tagtoa.hotel.home'))->assertOk()->assertSee('Belle Vue');
        $r->assertSee('0 / 6')->assertSee('Optionnel');
        foreach (['Chambres', 'Tarifs des chambres', 'Restaurant et bar', 'Services aux clients', 'QR / NFC des chambres', 'Personnel'] as $titre) {
            $r->assertSee($titre);
        }
        $r->assertSee('Ajoutez d\'abord des chambres');
    }

    public function test_steps_turn_done_as_the_hotel_is_set_up_and_count_only_this_hotel(): void
    {
        $this->compte();
        $a = $this->commerce('hotel', 'Hôtel Alpha');
        HotelRoom::allTenants()->create(['tenant_id' => $a->id, 'number' => '101', 'is_active' => true]);
        HotelRoom::allTenants()->create(['tenant_id' => $a->id, 'number' => '102', 'is_active' => true]);
        $t = HotelRoomType::allTenants()->create(['tenant_id' => $a->id, 'name' => 'Suite', 'is_active' => true]);
        HotelRatePlan::allTenants()->create(['tenant_id' => $a->id, 'room_type_id' => $t->id, 'kind' => 'night', 'slot' => 0, 'price' => 100, 'currency' => 'HTG', 'is_active' => true]);
        HotelService::allTenants()->create(['tenant_id' => $a->id, 'kind' => 'housekeeping', 'name' => 'Ménage', 'is_active' => true]);
        Menu::allTenants()->create(['tenant_id' => $a->id, 'name' => 'Resto', 'alias' => 'resto-a', 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true]);

        // Un AUTRE hôtel du même compte, très équipé : ses chambres ne comptent pas pour Alpha.
        $b = $this->commerce('hotel', 'Hôtel Beta');
        foreach (['1', '2', '3', '4', '5'] as $n) {
            HotelRoom::allTenants()->create(['tenant_id' => $b->id, 'number' => $n, 'is_active' => true]);
        }
        Tenant::switchTo($a->id);

        $html = $this->get(route('tagtoa.hotel.home'))->assertOk()->getContent();
        $this->assertStringContainsString('Hôtel Alpha', $html);
        $this->assertStringContainsString('2 chambres', $html);
        $this->assertStringNotContainsString('5 chambres', $html);
        $this->assertStringContainsString('1 tarif actif', $html);
        $this->assertStringContainsString('1 service actif', $html);
        $this->assertStringContainsString('1 menu', $html);
        $this->assertStringContainsString('0 / 2 chambres équipées', $html);
        $this->assertStringContainsString('3 / 6', $html);   // chambres + tarifs + services : le menu (restaurant/bar) est OPTIONNEL, hors du total
    }

    public function test_the_qr_step_is_done_only_when_every_room_has_its_support(): void
    {
        $this->compte();
        $h = $this->commerce('hotel', 'Belle Vue');
        $a = HotelRoom::allTenants()->create(['tenant_id' => $h->id, 'number' => '101', 'is_active' => true]);
        $b = HotelRoom::allTenants()->create(['tenant_id' => $h->id, 'number' => '102', 'is_active' => true]);
        // `stand_id` n'est pas remplissable en masse (seul le service d'activation l'écrit) : écriture directe ici.
        HotelRoom::allTenants()->whereKey($a->id)->update(['stand_id' => 11]);

        $html = $this->get(route('tagtoa.hotel.home'))->getContent();
        $this->assertStringContainsString('1 / 2 chambres équipées', $html);
        $this->assertStringContainsString('1 / 6', $html, 'Seules les chambres sont faites: le QR n\'est pas fini.');

        HotelRoom::allTenants()->whereKey($b->id)->update(['stand_id' => 12]);
        $html = $this->get(route('tagtoa.hotel.home'))->getContent();
        $this->assertStringContainsString('2 / 2 chambres équipées', $html);
        $this->assertStringContainsString('2 / 6', $html, 'Chambres + QR.');
    }

    public function test_the_staff_step_counts_only_active_hotel_staff_of_this_hotel(): void
    {
        $this->compte();
        $h = $this->commerce('hotel', 'Belle Vue');
        $autre = $this->commerce('hotel', 'Autre hôtel');
        Staff::allTenants()->create(['tenant_id' => $autre->id, 'name' => 'Paul', 'role' => 'hotel_staff', 'pin_hash' => 'x', 'is_active' => true]);
        Tenant::switchTo($h->id);
        $this->assertStringContainsString('0 employé', $this->get(route('tagtoa.hotel.home'))->getContent());

        Staff::allTenants()->create(['tenant_id' => $h->id, 'name' => 'Marie', 'role' => 'hotel_staff', 'pin_hash' => 'x', 'is_active' => true]);
        Staff::allTenants()->create(['tenant_id' => $h->id, 'name' => 'Inactif', 'role' => 'hotel_staff', 'pin_hash' => 'x', 'is_active' => false]);
        Staff::allTenants()->create(['tenant_id' => $h->id, 'name' => 'Caissier', 'role' => 'cashier', 'pin_hash' => 'x', 'is_active' => true]);
        $html = $this->get(route('tagtoa.hotel.home'))->getContent();
        $this->assertStringContainsString('1 employé', $html);
        $this->assertStringContainsString('1 / 6', $html);
    }

    public function test_an_optional_menu_never_counts_toward_progress_even_when_done(): void
    {
        $this->compte();
        $h = $this->commerce('hotel', 'Belle Vue');
        Menu::allTenants()->create(['tenant_id' => $h->id, 'name' => 'Resto', 'alias' => 'resto-z', 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true]);

        $html = $this->get(route('tagtoa.hotel.home'))->getContent();
        $this->assertStringContainsString('0 / 6', $html);
        $this->assertStringContainsString('1 menu', $html);
    }

    public function test_every_step_links_to_a_real_screen(): void
    {
        $this->compte();
        $this->commerce('hotel', 'Belle Vue');
        $html = $this->get(route('tagtoa.hotel.home'))->getContent();

        foreach ([route('tagtoa.hotel.rooms'), route('tagtoa.hotel.roomtypes'), route('tagtoa.hotel.services'), route('tagtoa.staff.index'), url('/tagtoa/menu')] as $u) {
            $this->assertStringContainsString('href="'.$u.'"', $html);
            $this->get($u)->assertOk();
        }
    }

    /* ---------- accueil : compte sans hôtel courant ---------- */

    public function test_an_account_without_a_hotel_is_offered_to_create_one_with_the_type_preselected(): void
    {
        $this->compte();
        $this->commerce('restaurant', 'Chez Rose');

        $this->get(route('tagtoa.hotel.home'))->assertOk()->assertSee('Créer mon hôtel')->assertSee('type=hotel', false)->assertDontSee('Ouvrir mon hôtel');

        $form = $this->get(route('tagtoa.business.create', ['type' => 'hotel']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="hotel"[^>]*selected/', $form);

        // Une valeur inconnue (ou un tableau) retombe sur « autre » : jamais d'erreur, jamais un type inventé.
        $inconnu = $this->get(route('tagtoa.business.create', ['type' => 'xxx']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="other"[^>]*selected/', $inconnu);
        $this->get('/tagtoa/business/new?type[]=hotel')->assertOk();
    }

    public function test_an_account_with_a_hotel_but_another_current_business_can_open_it(): void
    {
        $this->compte();
        $h = $this->commerce('hotel', 'Belle Vue');
        $r = $this->commerce('restaurant', 'Chez Rose');          // le restaurant est maintenant le commerce courant
        $this->assertSame($r->id, Tenant::id());

        $this->get(route('tagtoa.hotel.home'))->assertOk()->assertSee('Ouvrir mon hôtel')->assertSee('Belle Vue');

        $this->post(route('tagtoa.business.switch', $h->id), ['to' => 'hotel'])->assertRedirect(route('tagtoa.hotel.home'));
        $this->assertSame($h->id, Tenant::id());
        $this->get(route('tagtoa.hotel.home'))->assertOk()->assertSee('0 / 6');
    }

    public function test_switching_without_a_destination_still_goes_to_the_dashboard_home(): void
    {
        $this->compte();
        $h = $this->commerce('hotel', 'Belle Vue');
        $this->commerce('restaurant', 'Chez Rose');

        $this->post(route('tagtoa.business.switch', $h->id))->assertRedirect(route('tagtoa.hub'));
    }

    public function test_another_accounts_hotel_cannot_be_opened_or_listed(): void
    {
        $this->compte('c-b');
        $autre = $this->commerce('hotel', 'Hôtel Secret');
        $this->compte('c-a');
        $this->commerce('restaurant', 'Chez Rose');

        $this->get(route('tagtoa.hotel.home'))->assertOk()->assertDontSee('Hôtel Secret')->assertDontSee('Ouvrir mon hôtel');
        $this->post(route('tagtoa.business.switch', $autre->id), ['to' => 'hotel'])->assertSessionHas('error');
        $this->assertNotSame($autre->id, Tenant::id());
    }

    /* ---------- téléphone : tableaux en cartes ---------- */

    public function test_rooms_and_services_tables_become_cards_on_a_phone(): void
    {
        $this->compte();
        $h = $this->commerce('hotel', 'Belle Vue');
        HotelRoom::allTenants()->create(['tenant_id' => $h->id, 'number' => '101', 'is_active' => true]);
        HotelService::allTenants()->create(['tenant_id' => $h->id, 'kind' => 'housekeeping', 'name' => 'Ménage', 'is_active' => true]);

        foreach ([route('tagtoa.hotel.rooms'), route('tagtoa.hotel.services')] as $u) {
            $html = $this->get($u)->assertOk()->getContent();
            $this->assertStringContainsString('<table class="rcards"', $html);
            $this->assertStringContainsString('data-l="', $html);
            $this->assertStringContainsString('<td class="acts"', $html);
            // La règle qui fait le travail sur téléphone est dans la mise en page.
            $this->assertStringContainsString('.content table.rcards{display:block', $html);
        }
    }
}
