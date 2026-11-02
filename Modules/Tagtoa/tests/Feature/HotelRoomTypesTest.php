<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (HM-2) — types de chambre, tarifs par nuit / par moment, et ce qui est inclus
|--------------------------------------------------------------------------
| Le marchand déclare des types de chambre (« Chambre climatisée », « Suite »…) et, pour chacun, des tarifs : une NUIT
| et/ou un MOMENT (1 h, 2 h…), avec ce qui est inclus (petit déjeuner, piscine, transport…). La page publique de
| l'hôtel AFFICHE ces prix (aucune réservation ni paiement ici).
|
| Garanties : réservé au type « hôtel », isolé par commerce (404 sur l'identifiant d'un autre hôtel), jamais supprimé,
| validation serveur (durée, prix, devise, inclus), pas de doublon (une nuit / un même moment par type), texte libre
| nettoyé, page publique sans fuite entre hôtels.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRatePlan;
use Modules\Tagtoa\App\Models\Hotel\HotelRoomType;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomTypeRules as R;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelRoomTypesTest extends TestCase
{
    use RefreshDatabase;

    private function commerce(string $compte, string $nom, string $type = 'hotel'): Business
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $compte, 'name' => 'Rose']));
        Tenant::flush();

        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => $type, 'currency' => 'HTG']);
    }

    private function type(Business $b, string $nom = 'Chambre climatisée', bool $actif = true): HotelRoomType
    {
        return HotelRoomType::allTenants()->create(['tenant_id' => $b->id, 'name' => $nom, 'is_active' => $actif, 'sort' => 1]);
    }

    private function plan(HotelRoomType $t, array $o = []): HotelRatePlan
    {
        return HotelRatePlan::allTenants()->create(array_merge([
            'tenant_id' => $t->tenant_id, 'room_type_id' => $t->id, 'kind' => 'night', 'hours' => null, 'price' => 2500,
            'currency' => 'HTG', 'inclusions' => ['keys' => ['breakfast'], 'custom' => []], 'sort' => 1, 'is_active' => true,
        ], $o));
    }

    private function menu(Business $b): Menu
    {
        return Menu::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Hôtel', 'alias' => 'hotel-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true, 'show_prices' => true]);
    }

    private function page(Menu $m): string
    {
        // Page PUBLIQUE : on la lit comme un visiteur anonyme (un marchand connecté a sa propre portée tenant).
        \Illuminate\Support\Facades\Auth::guard()->forgetUser();
        Tenant::flush();

        return $this->get(route('tagtoa.menu.show', $m->alias))->assertOk()->getContent();
    }

    /* ---------- accès ---------- */

    public function test_the_section_is_reserved_to_hotels(): void
    {
        $this->commerce('c-a', 'Resto', 'restaurant');

        $this->get(route('tagtoa.hotel.roomtypes'))->assertRedirect(route('tagtoa.hub'));
        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => 'X'])->assertRedirect(route('tagtoa.hub'));
        $this->assertSame(0, HotelRoomType::allTenants()->count());
    }

    public function test_the_hotel_menu_links_to_the_page_and_it_renders(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $this->type($b, 'Suite Royale');

        // La page s'ouvre, et le lien « Tarifs des chambres » est dans le menu de l'hôtel (barre latérale).
        $this->get(route('tagtoa.hotel.roomtypes'))->assertOk()->assertSee('Suite Royale')->assertSee('Inclus dans ce prix')
            ->assertSee('/tagtoa/hotel/room-types', false);
    }

    /* ---------- types ---------- */

    public function test_a_type_is_created_updated_and_toggled_but_never_deleted(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');

        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => '  Suite  ', 'capacity' => 4, 'description' => 'Vue mer'])->assertSessionHasNoErrors();
        $t = HotelRoomType::sole();
        $this->assertSame(['Suite', 4, 'Vue mer', $b->id, true], [$t->name, $t->capacity, $t->description, $t->tenant_id, $t->is_active]);

        $this->put(route('tagtoa.hotel.roomtypes.update', $t->id), ['name' => 'Suite Deluxe'])->assertSessionHasNoErrors();
        $this->assertSame('Suite Deluxe', $t->fresh()->name);
        $this->assertNull($t->fresh()->description, 'Vide = effacé, pas conservé.');

        $this->post(route('tagtoa.hotel.roomtypes.toggle', $t->id));
        $this->assertFalse($t->fresh()->is_active);
        $this->post(route('tagtoa.hotel.roomtypes.toggle', $t->id));
        $this->assertTrue($t->fresh()->is_active);
        $this->assertSame(1, HotelRoomType::allTenants()->count());
    }

    public function test_type_validation(): void
    {
        $this->commerce('c-a', 'Hôtel Alpha');

        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => 'X', 'capacity' => 0])->assertSessionHasErrors('capacity');
        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => 'X', 'capacity' => 51])->assertSessionHasErrors('capacity');
        $this->assertSame(0, HotelRoomType::allTenants()->count());
    }

    public function test_the_number_of_types_is_capped(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        for ($i = 0; $i < R::MAX_TYPES; $i++) {
            $this->type($b, 'T'.$i);
        }

        $this->post(route('tagtoa.hotel.roomtypes.store'), ['name' => 'De trop'])->assertSessionHas('error');
        $this->assertSame(R::MAX_TYPES, HotelRoomType::allTenants()->count());
    }

    /* ---------- tarifs ---------- */

    public function test_a_night_and_a_moment_are_created_with_their_inclusions(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $t = $this->type($b);

        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), ['kind' => 'night', 'hours' => 3, 'price' => '2500.5', 'currency' => 'htg',
            'inclusions' => ['breakfast', 'pool', 'airport_transfer'], 'custom' => "Jacuzzi\nBouteille de rhum"])->assertSessionHasNoErrors();
        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), ['kind' => 'block', 'hours' => 2, 'price' => 800, 'currency' => 'HTG'])->assertSessionHasNoErrors();

        $nuit = HotelRatePlan::where('kind', 'night')->sole();
        $this->assertNull($nuit->hours, 'Une nuit n\'a pas de durée, même si le formulaire en envoie une.');
        $this->assertEquals(2500.5, $nuit->price);
        $this->assertSame('HTG', $nuit->currency);
        $this->assertSame($b->id, $nuit->tenant_id);
        $this->assertSame(['breakfast', 'pool', 'airport_transfer'], $nuit->inclusions['keys']);
        $this->assertSame(['Jacuzzi', 'Bouteille de rhum'], $nuit->inclusions['custom']);

        $moment = HotelRatePlan::where('kind', 'block')->sole();
        $this->assertSame(2, $moment->hours);
        $this->assertSame(['keys' => [], 'custom' => []], $moment->inclusions);
    }

    /** @dataProvider invalidPlans */
    public function test_invalid_plans_are_refused(array $payload, string $champ): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $t = $this->type($b);

        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), $payload + ['kind' => 'night', 'price' => 100, 'currency' => 'HTG'])->assertSessionHasErrors($champ);
        $this->assertSame(0, HotelRatePlan::allTenants()->count());
    }

    public static function invalidPlans(): array
    {
        return [
            'moment sans durée'      => [['kind' => 'block'], 'hours'],
            'durée hors catalogue'   => [['kind' => 'block', 'hours' => 5], 'hours'],
            'type de tarif inconnu'  => [['kind' => 'semaine'], 'kind'],
            'prix négatif'           => [['price' => -1], 'price'],
            'prix énorme'            => [['price' => R::PRICE_MAX + 1], 'price'],
            'prix absent'            => [['price' => ''], 'price'],
            'devise sans taux'       => [['currency' => 'EUR'], 'currency'],
            'inclus inconnu'         => [['inclusions' => ['helicopter']], 'inclusions.0'],
            'inclus pas une liste'   => [['inclusions' => 'breakfast'], 'inclusions'],
        ];
    }

    public function test_a_night_or_the_same_moment_cannot_be_declared_twice_for_a_type(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $t = $this->type($b);
        $this->plan($t);
        $this->plan($t, ['kind' => 'block', 'hours' => 2, 'price' => 800]);

        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), ['kind' => 'night', 'price' => 1, 'currency' => 'HTG'])->assertSessionHasErrors('kind');
        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), ['kind' => 'block', 'hours' => 2, 'price' => 1, 'currency' => 'HTG'])->assertSessionHasErrors('kind');
        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), ['kind' => 'block', 'hours' => 3, 'price' => 1000, 'currency' => 'HTG'])->assertSessionHasNoErrors();
        $this->assertSame(3, HotelRatePlan::allTenants()->count());

        // Autre type de chambre : mêmes durées permises.
        $autre = $this->type($b, 'Suite');
        $this->post(route('tagtoa.hotel.rateplans.store', $autre->id), ['kind' => 'night', 'price' => 5000, 'currency' => 'HTG'])->assertSessionHasNoErrors();
    }

    public function test_updating_a_plan_can_keep_its_own_slot_but_not_take_another(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $t = $this->type($b);
        $nuit = $this->plan($t);
        $m2 = $this->plan($t, ['kind' => 'block', 'hours' => 2, 'price' => 800]);

        $this->put(route('tagtoa.hotel.rateplans.update', $nuit->id), ['kind' => 'night', 'price' => 3000, 'currency' => 'HTG'])->assertSessionHasNoErrors();
        $this->assertEquals(3000, $nuit->fresh()->price);

        $this->put(route('tagtoa.hotel.rateplans.update', $m2->id), ['kind' => 'night', 'price' => 1, 'currency' => 'HTG'])->assertSessionHasErrors('kind');
        $this->assertSame('block', $m2->fresh()->kind);
    }

    public function test_a_foreign_currency_is_accepted_only_with_an_active_rate(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $t = $this->type($b);
        $payload = ['kind' => 'night', 'price' => 50, 'currency' => 'USD'];

        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), $payload)->assertSessionHasErrors('currency');

        app(CurrencyRateService::class)->save($b->id, 'HTG', ['code' => 'USD', 'rate' => 132, 'confirm_jump' => true]);
        $this->post(route('tagtoa.hotel.rateplans.store', $t->id), $payload)->assertSessionHasNoErrors();
        $this->assertSame('USD', HotelRatePlan::sole()->currency);
    }

    public function test_toggling_a_plan_never_deletes_it(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $p = $this->plan($this->type($b));

        $this->post(route('tagtoa.hotel.rateplans.toggle', $p->id));
        $this->assertFalse($p->fresh()->is_active);
        $this->post(route('tagtoa.hotel.rateplans.toggle', $p->id));
        $this->assertTrue($p->fresh()->is_active);
        $this->assertSame(1, HotelRatePlan::allTenants()->count());
    }

    /* ---------- texte libre ---------- */

    public function test_free_text_inclusions_are_cleaned_bounded_and_deduplicated(): void
    {
        $c = R::cleanInclusions(['pool', 'pool', 'inconnu', 42], "  <b>Jacuzzi</b>  \n\njacuzzi\n".str_repeat('é', 90)."\n<script>alert(1)</script>\nA\nB\nC\nD\nE\nF");

        $this->assertSame(['pool'], $c['keys'], 'Doublons et clés inconnues écartés.');
        $this->assertSame('Jacuzzi', $c['custom'][0], 'Balises retirées.');
        $this->assertCount(R::MAX_CUSTOM_INCLUSIONS, $c['custom']);
        $this->assertNotContains('jacuzzi', $c['custom'], 'Doublon (casse ignorée).');
        $this->assertSame(R::CUSTOM_MAX_LENGTH, mb_strlen($c['custom'][1]));
        foreach ($c['custom'] as $l) {
            $this->assertStringNotContainsString('<', $l);
        }
    }

    public function test_describing_tolerates_missing_or_malformed_storage_and_unknown_keys(): void
    {
        $this->assertSame([], R::describe(null));
        $this->assertSame([], R::describe('breakfast'));
        $this->assertSame([], R::describe(['keys' => 'x', 'custom' => 7]));
        $this->assertSame([], R::describe(['keys' => ['disparu_du_catalogue']]));

        $d = R::describe(['keys' => ['pool', 'disparu'], 'custom' => ['jacuzzi', '  ']]);
        $this->assertSame(['Accès à la piscine', 'jacuzzi'], array_column($d, 'label'), 'Un libellé libre en minuscules reste affiché.');
    }

    /* ---------- isolation ---------- */

    public function test_another_hotel_cannot_read_or_touch_these_types_and_plans(): void
    {
        $a = $this->commerce('c-a', 'Hôtel Alpha');
        $ta = $this->type($a, 'Suite secrète');
        $pa = $this->plan($ta);
        $b = $this->commerce('c-b', 'Hôtel Beta');

        $this->get(route('tagtoa.hotel.roomtypes'))->assertOk()->assertDontSee('Suite secrète');
        $this->put(route('tagtoa.hotel.roomtypes.update', $ta->id), ['name' => 'Piraté'])->assertNotFound();
        $this->post(route('tagtoa.hotel.roomtypes.toggle', $ta->id))->assertNotFound();
        $this->post(route('tagtoa.hotel.rateplans.store', $ta->id), ['kind' => 'block', 'hours' => 1, 'price' => 1, 'currency' => 'HTG'])->assertNotFound();
        $this->put(route('tagtoa.hotel.rateplans.update', $pa->id), ['kind' => 'night', 'price' => 1, 'currency' => 'HTG'])->assertNotFound();
        $this->post(route('tagtoa.hotel.rateplans.toggle', $pa->id))->assertNotFound();

        $this->assertSame('Suite secrète', $ta->fresh()->name);
        $this->assertEquals(2500, $pa->fresh()->price);
        $this->assertTrue($pa->fresh()->is_active);
        $this->assertSame(1, HotelRatePlan::allTenants()->count());
    }

    /* ---------- page publique ---------- */

    public function test_the_public_page_shows_rooms_with_prices_and_inclusions(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $menu = $this->menu($b);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 400, 'is_available' => true]);
        $t = $this->type($b, 'Chambre climatisée');
        $t->update(['capacity' => 2, 'description' => 'Calme et propre']);
        $this->plan($t, ['inclusions' => ['keys' => ['breakfast', 'pool', 'air_con'], 'custom' => ['Jacuzzi']]]);
        $this->plan($t, ['kind' => 'block', 'hours' => 2, 'price' => 800, 'inclusions' => ['keys' => ['fan'], 'custom' => []]]);

        $html = $this->page($menu);

        $this->assertStringContainsString('id="rooms"', $html);
        $this->assertStringContainsString('Chambre climatisée', $html);
        $this->assertStringContainsString('Calme et propre', $html);
        $this->assertStringContainsString('Nuit', $html);
        $this->assertStringContainsString('2 h', $html);
        $this->assertStringContainsString('Petit déjeuner', $html);
        $this->assertStringContainsString('Accès à la piscine', $html);
        $this->assertStringContainsString('Jacuzzi', $html);
        $this->assertStringContainsString('Chambre avec ventilateur', $html);
        $this->assertStringContainsString('Inclus dans ce prix', $html);
        // « Chambres » devient un onglet (avec le rayon restaurant), et la page du restaurant reste là.
        $this->assertStringContainsString('id="sectabs"', $html);
        $this->assertStringContainsString('class="sectab" data-sec="rooms"', $html);
        $this->assertStringContainsString('class="sectab" data-sec="restaurant"', $html);
        $this->assertStringContainsString('Griot', $html);
    }

    public function test_a_hotel_with_rooms_but_no_menu_items_still_shows_its_rooms(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $menu = $this->menu($b);
        $this->plan($this->type($b, 'Suite Royale'));

        $html = $this->page($menu);
        $this->assertStringContainsString('Suite Royale', $html);
        $this->assertStringNotContainsString('Le menu arrive bientôt', $html);
        $this->assertStringNotContainsString('id="sectabs"', $html, 'Une seule section : pas d\'onglets.');
    }

    public function test_inactive_types_and_plans_and_empty_types_are_never_shown(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $menu = $this->menu($b);
        $actif = $this->type($b, 'Visible');
        $this->plan($actif, ['price' => 111]);
        $this->plan($actif, ['kind' => 'block', 'hours' => 1, 'price' => 222, 'is_active' => false]);
        $this->plan($this->type($b, 'TypeDésactivé', false), ['price' => 333]);
        $this->type($b, 'SansTarif');
        $this->plan($this->type($b, 'SeulementDésactivé'), ['is_active' => false]);

        $html = $this->page($menu);
        $this->assertStringContainsString('Visible', $html);
        $this->assertStringNotContainsString('TypeDésactivé', $html);
        $this->assertStringNotContainsString('SansTarif', $html);
        $this->assertStringNotContainsString('SeulementDésactivé', $html);
        $this->assertStringNotContainsString('222', $html, 'Le tarif désactivé ne s\'affiche pas.');
    }

    public function test_the_public_page_never_shows_another_hotels_rooms(): void
    {
        $a = $this->commerce('c-a', 'Hôtel Alpha');
        $menuA = $this->menu($a);
        $this->plan($this->type($a, 'ChambreAlpha'));
        $b = $this->commerce('c-b', 'Hôtel Beta');
        $this->plan($this->type($b, 'ChambreBeta'));

        $html = $this->page($menuA);
        $this->assertStringContainsString('ChambreAlpha', $html);
        $this->assertStringNotContainsString('ChambreBeta', $html);
    }

    public function test_the_type_filter_alone_blocks_another_hotels_type_even_with_inconsistent_data(): void
    {
        $a = $this->commerce('c-a', 'Hôtel Alpha');
        $menuA = $this->menu($a);
        $this->plan($this->type($a, 'ChambreAlpha'));
        $b = $this->commerce('c-b', 'Hôtel Beta');
        $tb = $this->type($b, 'ChambreBetaIsolée');
        // Donnée incohérente : un tarif marqué « hôtel A » accroché au type de l'hôtel B. Le type de B ne doit
        // JAMAIS apparaître sur la page de A (filtre tenant sur les types, indépendant de celui des tarifs).
        $this->plan($tb, ['tenant_id' => $a->id]);

        $html = $this->page($menuA);
        $this->assertStringContainsString('ChambreAlpha', $html);
        $this->assertStringNotContainsString('ChambreBetaIsolée', $html);
    }

    public function test_names_and_free_text_are_escaped_on_the_public_page(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $menu = $this->menu($b);
        $t = $this->type($b, '<script>alert(1)</script>Suite');
        $this->plan($t, ['inclusions' => ['keys' => [], 'custom' => ['<img src=x onerror=alert(2)>']]]);

        $html = $this->page($menu);
        $this->assertStringNotContainsString('<script>alert(1)</script>Suite', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(2)>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;Suite', $html);
    }

    public function test_a_foreign_currency_price_shows_its_conversion_when_a_rate_exists(): void
    {
        $b = $this->commerce('c-a', 'Hôtel Alpha');
        $menu = $this->menu($b);
        app(CurrencyRateService::class)->save($b->id, 'HTG', ['code' => 'USD', 'rate' => 100, 'confirm_jump' => true]);
        $this->plan($this->type($b), ['price' => 50, 'currency' => 'USD']);

        $html = $this->page($menu);
        $this->assertStringContainsString('≈', $html);
    }

    public function test_a_menu_of_a_non_hotel_is_unchanged(): void
    {
        $r = $this->commerce('c-r', 'Resto', 'restaurant');
        $menu = $this->menu($r);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Pâtes', 'price' => 10, 'is_available' => true]);

        $html = $this->page($menu);
        $this->assertStringNotContainsString('id="rooms"', $html);
        $this->assertStringNotContainsString('id="sectabs"', $html);
    }

    /* ---------- migration ---------- */

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $m = require __DIR__.'/../../Database/migrations/2026_11_02_000196_create_hotel_room_types_and_rate_plans.php';
        $m->up();
        $m->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_room_types') && Schema::hasTable('tagtoa_hotel_rate_plans'));
        $m->down();
        $this->assertFalse(Schema::hasTable('tagtoa_hotel_room_types') || Schema::hasTable('tagtoa_hotel_rate_plans'));
        $m->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_rate_plans'));
    }
}
