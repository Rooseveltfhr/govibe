<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Menu\BusinessProfile;
use Modules\Tagtoa\Tests\TestCase;

/**
 * MENU s'adapte au métier : hôtel, restaurant, bar, club, lounge, café.
 *
 * Ces tests touchent la vraie base : ils vérifient que les champs métier
 * survivent à un aller-retour SQL (colonne JSON `specs`) et qu'une valeur
 * inventée n'y arrive jamais.
 */
class MenuBusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $type, array $specs): Item
    {
        $menu = Menu::create([
            'tenant_id' => 't-1', 'name' => 'Établissement', 'type' => $type,
            'alias' => 'e-'.uniqid(), 'currency' => 'HTG', 'is_active' => true,
        ]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Catégorie', 'is_active' => true]);

        return Item::create([
            'menu_id'  => $menu->id, 'category_id' => $cat->id, 'name' => 'Article',
            'price'    => 100,
            'specs'    => BusinessProfile::sanitize($type, $specs),
        ]);
    }

    public function test_every_type_offered_in_the_dropdown_has_a_profile(): void
    {
        // Un type proposé au marchand sans profil retomberait en silence sur
        // « Autre » : il perdrait les champs de son métier sans comprendre pourquoi.
        foreach (array_keys(Menu::TYPES) as $type) {
            $this->assertArrayHasKey($type, BusinessProfile::PROFILES, "Type « $type » sans profil.");
        }
    }

    public function test_a_hotel_room_keeps_its_details_through_the_database(): void
    {
        $room = $this->item('hotel', [
            'room_type' => 'Suite',
            'capacity'  => 4,
            'beds'      => 2,
            'area'      => 38.5,
            'view'      => 'Mer',
            'amenities' => ['Climatisation', 'Wi-Fi', 'Balcon'],
            'breakfast' => '1',
        ]);

        $fresh = Item::find($room->id);

        $this->assertSame('Suite', $fresh->specs['room_type']);
        $this->assertSame(4, $fresh->specs['capacity']);
        $this->assertSame(38.5, $fresh->specs['area']);
        $this->assertSame(['Climatisation', 'Wi-Fi', 'Balcon'], $fresh->specs['amenities']);
        $this->assertTrue($fresh->specs['breakfast']);
    }

    public function test_a_restaurant_dish_and_a_bar_drink_get_their_own_fields(): void
    {
        $dish = $this->item('restaurant', ['prep_time' => 20, 'spice' => 'Piquant', 'allergens' => ['Fruits de mer']]);
        $drink = $this->item('bar', ['serving' => 'Bouteille', 'volume' => 33, 'abv' => 4.7]);

        $this->assertSame(['prep_time' => 20, 'spice' => 'Piquant', 'allergens' => ['Fruits de mer']],
            Item::find($dish->id)->specs);
        $this->assertSame(['serving' => 'Bouteille', 'volume' => 33, 'abv' => 4.7],
            Item::find($drink->id)->specs);
    }

    public function test_a_field_from_another_trade_never_reaches_the_database(): void
    {
        // Un formulaire trafiqué envoie des clés d'un autre métier et une clé
        // inventée : rien de tout cela ne doit être enregistré.
        $dish = $this->item('restaurant', [
            'prep_time' => 15,
            'capacity'  => 8,        // champ d'hôtel
            'abv'       => 40,       // champ de bar
            'is_admin'  => true,     // clé inventée
        ]);

        $this->assertSame(['prep_time' => 15], Item::find($dish->id)->specs);
    }

    public function test_an_item_without_any_trade_field_stores_null_not_an_empty_object(): void
    {
        $item = $this->item('restaurant', []);

        $this->assertNull(Item::find($item->id)->specs);
    }

    public function test_only_a_hotel_prices_per_night(): void
    {
        $this->assertSame('/ nuit', BusinessProfile::priceSuffix('hotel'));

        foreach (['restaurant', 'bar', 'club', 'lounge', 'cafe', 'other'] as $type) {
            $this->assertNull(BusinessProfile::priceSuffix($type), "« $type » ne se facture pas à la nuit.");
        }
    }
}
