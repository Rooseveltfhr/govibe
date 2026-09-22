<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| Order::sanitizeServiceTypes() / Order::serviceTypesFor() — PUR (aucun
| Eloquent, aucun I/O). Un menu déclare quels modes de service il offre au
| client, un sous-ensemble de Order::ORDER_TYPES (dine_in/pickup/delivery).
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\Tests\TestCase;

class OrderServiceTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_something_other_than_an_array_means_no_restriction(): void
    {
        $this->assertNull(Order::sanitizeServiceTypes(null));
        $this->assertNull(Order::sanitizeServiceTypes('dine_in'));
    }

    public function test_unknown_codes_are_dropped_silently(): void
    {
        $this->assertSame(['dine_in'], Order::sanitizeServiceTypes(['dine_in', 'teleportation']));
    }

    public function test_selecting_every_mode_collapses_to_no_restriction(): void
    {
        $this->assertNull(Order::sanitizeServiceTypes(['dine_in', 'pickup', 'delivery']));
    }

    public function test_an_empty_selection_never_blocks_all_ordering(): void
    {
        // Rien de coché nulle part : pas de restriction plutôt qu'une
        // sélection vide qui empêcherait toute commande.
        $this->assertNull(Order::sanitizeServiceTypes([]));
    }

    public function test_a_partial_selection_is_kept_as_is(): void
    {
        $this->assertSame(['delivery', 'pickup'], Order::sanitizeServiceTypes(['delivery', 'pickup']));
    }

    public function test_a_menu_without_a_restriction_offers_every_mode(): void
    {
        $this->assertSame(Order::ORDER_TYPES, Order::serviceTypesFor(null));
    }

    public function test_a_restricted_menu_offers_only_its_chosen_modes(): void
    {
        $this->assertSame(['delivery'], Order::serviceTypesFor(['delivery']));
    }
}
