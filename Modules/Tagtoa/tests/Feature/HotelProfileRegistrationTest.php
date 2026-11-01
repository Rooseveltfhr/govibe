<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H1) — le profil propre à l'hôtel, dans le formulaire d'inscription existant
|--------------------------------------------------------------------------
| Les champs d'hôtel sont conditionnels (type = hôtel), tous facultatifs, validés par le
| serveur. Les autres types d'établissement fonctionnent exactement comme avant.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelProfile;
use Modules\Tagtoa\App\Services\Hotel\HotelProfileService;
use Modules\Tagtoa\App\Support\Hotel\HotelCatalog;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelProfileRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $id = 'compte-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id]));
        Tenant::flush();
    }

    private function base(array $extra = []): array
    {
        return array_merge(['name' => 'Hôtel Belle Vue', 'type' => 'hotel', 'currency' => 'HTG', 'sells_products' => '1', 'sells_services' => '1'], $extra);
    }

    private function profil(string $businessId): ?HotelProfile
    {
        return HotelProfile::allTenants()->where('tenant_id', $businessId)->first();
    }

    public function test_a_hotel_is_created_through_the_existing_form_with_its_specific_fields(): void
    {
        Storage::fake('public');
        $this->compte();

        $this->post(route('tagtoa.business.store'), $this->base([
            'address' => 'Route de Delmas 33',
            'hotel' => [
                'description' => 'Confort au cœur de la ville', 'rooms_count' => 24,
                'reception_phone' => '+509 3711-1111', 'reception_email' => 'reception@bellevue.test',
                'reception_24h' => '0', 'reception_hours' => '7h – 22h',
                'facilities' => ['wifi', 'pool', 'generator'], 'languages' => ['ht', 'fr'],
                'room_service_enabled' => '1', 'guest_info' => 'Wi-Fi : BelleVue / petit-déjeuner 7h-10h',
            ],
            'hotel_cover' => UploadedFile::fake()->image('cover.jpg', 800, 400),
        ]))->assertRedirect(route('tagtoa.hub'))->assertSessionHasNoErrors();

        $business = Business::where('name', 'Hôtel Belle Vue')->firstOrFail();
        $this->assertSame('hotel', $business->type);

        $p = $this->profil($business->id);
        $this->assertNotNull($p);
        $this->assertSame(24, $p->rooms_count);
        $this->assertSame('reception@bellevue.test', $p->reception_email);
        $this->assertFalse($p->reception_24h);
        $this->assertSame('7h – 22h', $p->reception_hours);
        $this->assertEqualsCanonicalizing(['wifi', 'pool', 'generator'], $p->facilities);
        $this->assertEqualsCanonicalizing(['ht', 'fr'], $p->languages);
        $this->assertTrue($p->room_service_enabled);
        $this->assertNotNull($p->cover_path);
        Storage::disk('public')->assertExists($p->cover_path);
        $this->assertStringStartsWith('tagtoa/hotel-covers/', $p->cover_path);
    }

    public function test_a_hotel_without_any_specific_field_gets_safe_defaults(): void
    {
        $this->compte();

        $this->post(route('tagtoa.business.store'), $this->base())->assertRedirect()->assertSessionHasNoErrors();

        $p = $this->profil(Business::first()->id);
        $this->assertNotNull($p, 'Un hôtel a toujours un profil, même vide.');
        $this->assertTrue($p->reception_24h);
        $this->assertFalse($p->room_service_enabled);
        $this->assertNull($p->rooms_count);
        $this->assertSame([], $p->facilities);
        $this->assertSame([], $p->languages);
    }

    /* ---------- les autres types ne bougent pas ---------- */

    /** @dataProvider otherTypes */
    public function test_other_business_types_work_as_before_and_never_get_a_hotel_profile(string $type): void
    {
        $this->compte();

        // Même une requête qui porte des champs d'hôtel (ou des valeurs invalides) est ignorée.
        $this->post(route('tagtoa.business.store'), $this->base([
            'name' => 'Commerce test', 'type' => $type,
            'hotel' => ['rooms_count' => 99999, 'reception_email' => 'pas-un-email', 'facilities' => ['inconnu']],
        ]))->assertRedirect(route('tagtoa.hub'))->assertSessionHasNoErrors();

        $b = Business::where('name', 'Commerce test')->firstOrFail();
        $this->assertSame($type, $b->type);
        $this->assertNull($this->profil($b->id));
        $this->assertSame(0, DB::table('tagtoa_hotel_profiles')->count());
    }

    public static function otherTypes(): array
    {
        return ['restaurant' => ['restaurant'], 'boutique' => ['boutique'], 'bar' => ['bar'], 'other' => ['other']];
    }

    public function test_a_non_hotel_form_page_still_renders_and_hides_the_hotel_block(): void
    {
        $this->compte();

        $html = $this->get(route('tagtoa.business.create'))->assertOk()->getContent();

        $this->assertStringContainsString('id="hotel-fields" style="display:none"', $html);
        $this->assertStringContainsString("this.value === 'hotel'", $html);
    }

    /* ---------- validation ---------- */

    /** @dataProvider invalidInputs */
    public function test_invalid_hotel_input_is_refused_and_nothing_is_created(array $hotel, string $errorKey): void
    {
        $this->compte();

        $this->post(route('tagtoa.business.store'), $this->base(['hotel' => $hotel]))->assertSessionHasErrors($errorKey);

        $this->assertSame(0, Business::count());
        $this->assertSame(0, DB::table('tagtoa_hotel_profiles')->count());
    }

    public static function invalidInputs(): array
    {
        return [
            'chambres à zéro'      => [['rooms_count' => 0], 'hotel.rooms_count'],
            'chambres absurdes'    => [['rooms_count' => 99999], 'hotel.rooms_count'],
            'e-mail invalide'      => [['reception_email' => 'pas-un-email'], 'hotel.reception_email'],
            'téléphone invalide'   => [['reception_phone' => '<script>alert(1)</script>'], 'hotel.reception_phone'],
            'installation inconnue' => [['facilities' => ['teleporteur']], 'hotel.facilities.0'],
            'langue non supportée' => [['languages' => ['xx']], 'hotel.languages.0'],
            'description trop longue' => [['description' => str_repeat('a', 1001)], 'hotel.description'],
        ];
    }

    public function test_the_cover_must_be_a_real_image(): void
    {
        Storage::fake('public');
        $this->compte();

        $this->post(route('tagtoa.business.store'), $this->base([
            'hotel_cover' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ]))->assertSessionHasErrors('hotel_cover');

        $this->assertSame(0, Business::count());
    }

    /* ---------- modification et isolation ---------- */

    public function test_editing_a_hotel_updates_its_profile_and_keeps_the_cover_when_none_is_sent(): void
    {
        Storage::fake('public');
        $this->compte();
        $this->post(route('tagtoa.business.store'), $this->base([
            'hotel' => ['rooms_count' => 10], 'hotel_cover' => UploadedFile::fake()->image('c.jpg'),
        ]))->assertSessionHasNoErrors();
        $b = Business::first();
        $cover = $this->profil($b->id)->cover_path;

        $this->put(route('tagtoa.business.update', $b->id), $this->base(['hotel' => ['rooms_count' => 30, 'facilities' => ['spa']]]))
            ->assertSessionHasNoErrors();

        $p = $this->profil($b->id);
        $this->assertSame(30, $p->rooms_count);
        $this->assertSame(['spa'], $p->facilities);
        $this->assertSame($cover, $p->cover_path, 'Corriger un champ ne doit pas effacer la couverture.');
        $this->assertSame(1, DB::table('tagtoa_hotel_profiles')->count(), 'Un commerce, un profil.');
    }

    public function test_the_edit_form_shows_the_saved_values(): void
    {
        $this->compte();
        $this->post(route('tagtoa.business.store'), $this->base(['hotel' => ['rooms_count' => 17, 'facilities' => ['pool'], 'languages' => ['ht']]]));
        $b = Business::first();

        $html = $this->get(route('tagtoa.business.edit', $b->id))->assertOk()->getContent();

        $this->assertStringContainsString('value="17"', $html);
        $this->assertMatchesRegularExpression('#name="hotel\[facilities\]\[\]" value="pool" checked#', $html);
        $this->assertMatchesRegularExpression('#name="hotel\[languages\]\[\]" value="ht" checked#', $html);
        $this->assertStringNotContainsString('id="hotel-fields" style="display:none"', $html);
    }

    public function test_two_hotels_of_one_account_keep_separate_profiles(): void
    {
        $this->compte();
        $this->post(route('tagtoa.business.store'), $this->base(['name' => 'Hôtel A', 'hotel' => ['rooms_count' => 10]]));
        $this->post(route('tagtoa.business.store'), $this->base(['name' => 'Hôtel B', 'hotel' => ['rooms_count' => 50]]));

        $a = Business::where('name', 'Hôtel A')->first();
        $b = Business::where('name', 'Hôtel B')->first();
        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(10, $this->profil($a->id)->rooms_count);
        $this->assertSame(50, $this->profil($b->id)->rooms_count);

        // Modifier B ne touche pas A, même quand A est le commerce courant.
        Tenant::switchTo($a->id);
        $this->put(route('tagtoa.business.update', $b->id), $this->base(['name' => 'Hôtel B', 'hotel' => ['rooms_count' => 60]]));
        $this->assertSame(10, $this->profil($a->id)->rooms_count);
        $this->assertSame(60, $this->profil($b->id)->rooms_count);
    }

    public function test_another_account_cannot_edit_or_see_my_hotel(): void
    {
        $this->compte('compte-1');
        $this->post(route('tagtoa.business.store'), $this->base(['hotel' => ['rooms_count' => 10]]));
        $mine = Business::first();

        $this->compte('compte-2');
        $this->put(route('tagtoa.business.update', $mine->id), $this->base(['hotel' => ['rooms_count' => 999]]))->assertNotFound();
        $this->get(route('tagtoa.business.edit', $mine->id))->assertNotFound();

        $this->assertSame(10, $this->profil($mine->id)->rooms_count);
    }

    public function test_the_profile_is_isolated_by_business_scope(): void
    {
        $this->compte('compte-1');
        $this->post(route('tagtoa.business.store'), $this->base(['name' => 'Hôtel A']));
        $a = Business::first();
        $this->compte('compte-2');
        $this->post(route('tagtoa.business.store'), $this->base(['name' => 'Hôtel B']));

        // Avec le commerce de B comme commerce courant, la portée automatique ne montre que le sien.
        $this->assertSame(1, HotelProfile::count());
        $this->assertNotSame($a->id, HotelProfile::first()->tenant_id);
    }

    public function test_switching_the_type_away_from_hotel_keeps_the_data_and_stops_writing_to_it(): void
    {
        $this->compte();
        $this->post(route('tagtoa.business.store'), $this->base(['hotel' => ['rooms_count' => 12]]));
        $b = Business::first();

        $this->put(route('tagtoa.business.update', $b->id), $this->base(['type' => 'restaurant', 'hotel' => ['rooms_count' => 99]]))
            ->assertSessionHasNoErrors();

        $this->assertSame('restaurant', $b->fresh()->type);
        $this->assertSame(12, $this->profil($b->id)->rooms_count, 'Rien n\'est écrit pour un non-hôtel ; rien n\'est perdu non plus.');
    }

    public function test_the_catalog_cleaning_is_pure_and_defensive(): void
    {
        $c = HotelCatalog::clean([
            'description' => '   ', 'rooms_count' => '', 'reception_24h' => '1', 'reception_hours' => '7h',
            'facilities' => ['wifi', 'wifi', 'bidon'], 'languages' => ['fr', 'zz'], 'guest_info' => ' Wi-Fi ',
        ]);

        $this->assertNull($c['description']);
        $this->assertNull($c['rooms_count']);
        $this->assertNull($c['reception_hours'], 'Les horaires n\'ont pas de sens quand la réception est ouverte 24h/24.');
        $this->assertSame(['wifi'], $c['facilities']);
        $this->assertSame(['fr'], $c['languages']);
        $this->assertSame('Wi-Fi', $c['guest_info']);
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000182_create_tagtoa_hotel_profiles_table.php';
        $mig->up();                                  // table déjà là : ne casse pas
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('tagtoa_hotel_profiles'));
        $mig->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('tagtoa_hotel_profiles'));
        $mig->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('tagtoa_hotel_profiles'));
    }
}
