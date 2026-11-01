<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H2) — gestion des chambres
|--------------------------------------------------------------------------
| Créer, modifier, activer/désactiver ; numéro unique DANS l'hôtel ; création en série bornée ;
| réservé au type « hôtel » ; isolation entre hôtels ; jamais de suppression.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomRules;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelRoomsTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $id = 'compte-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id]));
        Tenant::flush();
    }

    /** Crée un commerce du type demandé pour le compte, et le rend courant. */
    private function commerce(string $type = 'hotel', string $nom = 'Hôtel Belle Vue'): Business
    {
        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => $type, 'currency' => 'HTG']);
    }

    private function ajouter(array $extra = [])
    {
        return $this->post(route('tagtoa.hotel.rooms.store'), array_merge(['number' => '101'], $extra));
    }

    public function test_a_hotel_can_create_edit_and_list_rooms(): void
    {
        $this->compte();
        $b = $this->commerce();

        $this->ajouter(['name' => 'Vue mer', 'type' => 'deluxe', 'floor' => '1'])->assertSessionHasNoErrors();

        $room = HotelRoom::first();
        $this->assertSame($b->id, $room->tenant_id);
        $this->assertSame('101', $room->number);
        $this->assertSame('deluxe', $room->type);
        $this->assertTrue($room->is_active);

        $this->put(route('tagtoa.hotel.rooms.update', $room->id), ['number' => '101A', 'name' => '', 'type' => 'suite', 'floor' => '2', 'notes' => 'près de l\'ascenseur'])
            ->assertSessionHasNoErrors();
        $room->refresh();
        $this->assertSame('101A', $room->number);
        $this->assertNull($room->name, 'Chaîne vide → null.');
        $this->assertSame('suite', $room->type);

        $this->get(route('tagtoa.hotel.rooms'))->assertOk()->assertSee('101A')->assertSee('Suite');
    }

    public function test_the_room_number_is_unique_inside_one_hotel_but_not_across_hotels(): void
    {
        $this->compte();
        $this->commerce('hotel', 'Hôtel A');
        $this->ajouter()->assertSessionHasNoErrors();
        $this->ajouter()->assertSessionHasErrors('number');       // doublon dans le même hôtel
        $this->assertSame(1, HotelRoom::count());

        // Un second hôtel du même compte peut avoir sa propre chambre 101.
        $this->commerce('hotel', 'Hôtel B');
        $this->ajouter()->assertSessionHasNoErrors();
        $this->assertSame(1, HotelRoom::count(), 'Chaque hôtel ne voit que ses chambres.');
        $this->assertSame(2, DB::table('tagtoa_hotel_rooms')->count());
    }

    public function test_updating_a_room_to_its_own_number_is_not_a_duplicate(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter();
        $room = HotelRoom::first();

        $this->put(route('tagtoa.hotel.rooms.update', $room->id), ['number' => '101', 'name' => 'Nouveau nom'])->assertSessionHasNoErrors();
        $this->assertSame('Nouveau nom', $room->fresh()->name);

        $this->ajouter(['number' => '102']);
        $this->put(route('tagtoa.hotel.rooms.update', $room->id), ['number' => '102'])->assertSessionHasErrors('number');
    }

    /** @dataProvider invalidRooms */
    public function test_invalid_input_is_refused_and_nothing_is_created(array $input, string $key): void
    {
        $this->compte();
        $this->commerce();

        $this->ajouter($input)->assertSessionHasErrors($key);

        $this->assertSame(0, HotelRoom::count());
    }

    public static function invalidRooms(): array
    {
        return [
            'numéro vide'        => [['number' => ''], 'number'],
            'numéro trop long'   => [['number' => str_repeat('1', 21)], 'number'],
            'numéro avec balise' => [['number' => '<b>1</b>'], 'number'],
            'numéro commençant par un point' => [['number' => '.1'], 'number'],
            'type inconnu'       => [['type' => 'chateau'], 'type'],
            'nom trop long'      => [['name' => str_repeat('a', 81)], 'name'],
            'notes trop longues' => [['notes' => str_repeat('a', 301)], 'notes'],
        ];
    }

    public function test_a_room_can_be_deactivated_and_reactivated_but_never_deleted(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter();
        $room = HotelRoom::first();

        $this->post(route('tagtoa.hotel.rooms.toggle', $room->id))->assertSessionHas('success');
        $this->assertFalse($room->fresh()->is_active);
        $this->post(route('tagtoa.hotel.rooms.toggle', $room->id));
        $this->assertTrue($room->fresh()->is_active);

        $this->delete('/tagtoa/hotel/rooms/'.$room->id)->assertStatus(405);
        $this->assertSame(1, HotelRoom::count());
        $this->assertSame(2, AuditLog::whereIn('action', ['hotel.room.deactivated', 'hotel.room.activated'])->count());
    }

    /* ---------- création en série ---------- */

    public function test_bulk_creation_makes_a_range_and_skips_existing_numbers(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter(['number' => '103', 'name' => 'Existante']);

        $this->post(route('tagtoa.hotel.rooms.bulk'), ['from' => 101, 'to' => 105, 'type' => 'standard'])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['101', '102', '103', '104', '105'], HotelRoom::pluck('number')->all());
        $this->assertSame('Existante', HotelRoom::where('number', '103')->value('name'), 'Une chambre existante n\'est jamais écrasée.');
        $this->assertSame('standard', HotelRoom::where('number', '104')->value('type'));
    }

    public function test_bulk_creation_is_bounded_and_validated(): void
    {
        $this->compte();
        $this->commerce();

        $this->post(route('tagtoa.hotel.rooms.bulk'), ['from' => 1, 'to' => 1 + HotelRoomRules::BULK_MAX])->assertSessionHasErrors('to');
        $this->post(route('tagtoa.hotel.rooms.bulk'), ['from' => 20, 'to' => 10])->assertSessionHasErrors('to');
        $this->post(route('tagtoa.hotel.rooms.bulk'), ['from' => 'a', 'to' => 5])->assertSessionHasErrors('from');
        $this->post(route('tagtoa.hotel.rooms.bulk'), ['from' => 1, 'to' => HotelRoomRules::BULK_MAX])->assertSessionHasNoErrors();

        $this->assertSame(HotelRoomRules::BULK_MAX, HotelRoom::count());
    }

    /* ---------- réservé aux hôtels ---------- */

    /** @dataProvider otherTypes */
    public function test_only_hotels_can_manage_rooms(string $type): void
    {
        $this->compte();
        $this->commerce($type, 'Autre commerce');

        $this->get(route('tagtoa.hotel.rooms'))->assertRedirect(route('tagtoa.hub'))->assertSessionHas('error');
        $this->ajouter()->assertRedirect(route('tagtoa.hub'));
        $this->post(route('tagtoa.hotel.rooms.bulk'), ['from' => 1, 'to' => 3])->assertRedirect(route('tagtoa.hub'));

        $this->assertSame(0, DB::table('tagtoa_hotel_rooms')->count());
    }

    public static function otherTypes(): array
    {
        return ['restaurant' => ['restaurant'], 'boutique' => ['boutique'], 'bar' => ['bar']];
    }

    public function test_the_sidebar_shows_the_hotel_module_only_for_a_hotel(): void
    {
        $this->compte('compte-r');
        $this->commerce('restaurant', 'Chez Rose');
        $this->assertArrayNotHasKey('hotel', \Modules\Tagtoa\App\Support\DashboardModules::enabled('module'));

        $this->compte('compte-h');
        $this->commerce('hotel', 'Belle Vue');
        $mods = \Modules\Tagtoa\App\Support\DashboardModules::enabled('module');
        $this->assertArrayHasKey('hotel', $mods);
        $this->assertSame('/tagtoa/hotel/rooms', $mods['hotel']['children'][0]['url']);
    }

    /* ---------- isolation ---------- */

    public function test_another_hotel_cannot_update_toggle_or_see_my_rooms(): void
    {
        $this->compte('compte-a');
        $this->commerce('hotel', 'Hôtel A');
        $this->ajouter(['name' => 'Secrète']);
        $roomA = HotelRoom::first();

        $this->compte('compte-b');
        $this->commerce('hotel', 'Hôtel B');

        $this->put(route('tagtoa.hotel.rooms.update', $roomA->id), ['number' => '999'])->assertNotFound();
        $this->post(route('tagtoa.hotel.rooms.toggle', $roomA->id))->assertNotFound();
        $this->get(route('tagtoa.hotel.rooms'))->assertOk()->assertDontSee('Secrète');

        $fresh = DB::table('tagtoa_hotel_rooms')->where('id', $roomA->id)->first();
        $this->assertSame('101', $fresh->number);
        $this->assertSame(1, (int) $fresh->is_active);
    }

    public function test_an_account_without_a_business_is_refused(): void
    {
        $this->be(new GenericUser(['id' => 9, 'tenant_id' => null]));
        Tenant::flush();

        $this->get(route('tagtoa.hotel.rooms'))->assertForbidden();
    }

    public function test_room_names_are_escaped_in_the_page(): void
    {
        $this->compte();
        $this->commerce();
        $this->ajouter(['name' => '<script>alert(1)</script>']);

        $html = $this->get(route('tagtoa.hotel.rooms'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_the_page_shows_how_many_rooms_remain_to_add_from_the_profile(): void
    {
        $this->compte();
        $b = $this->commerce();
        \Modules\Tagtoa\App\Models\Hotel\HotelProfile::create(['tenant_id' => $b->id, 'rooms_count' => 10]);
        $this->ajouter();

        $this->get(route('tagtoa.hotel.rooms'))->assertOk()->assertSee('1 / 10', false)->assertSee('il en reste 9');
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000183_create_tagtoa_hotel_rooms_table.php';
        $mig->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_rooms'));
        $mig->down();
        $this->assertFalse(Schema::hasTable('tagtoa_hotel_rooms'));
        $mig->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_rooms'));
    }
}
