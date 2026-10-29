<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU / STORE / BOOKING — une panne d'écriture n'est pas une
| « commande vide »
|--------------------------------------------------------------------------
| Les trois écrans publics attrapaient \RuntimeException et faisaient
| passer le message dans un `match` à codes connus. Aucune fuite SQL —
| mais QueryException hérite de RuntimeException, donc une panne de base
| tombait dans la branche par défaut : « Votre commande est vide. »
|
| Deux conséquences, et la seconde est la pire :
|   1. le client lit que SON panier a un problème alors que l'écriture a
|      échoué côté serveur ; il recommence, relit la même phrase, s'en va ;
|   2. la réponse était un 422 — une erreur du CLIENT — donc rien n'alerte
|      personne. La commande est perdue sans trace visible.
*/

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Booking\BookingPage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Booking\BookingService;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Store\StoreOrderService;
use Modules\Tagtoa\Tests\TestCase;

class OrderErrorMaskingTest extends TestCase
{
    use RefreshDatabase;

    private function dbPanne(): QueryException
    {
        return new QueryException(
            'sqlite',
            'insert into "tagtoa_menu_orders" ("reference") values (?)',
            ['TAG-1'],
            new \Exception('SQLSTATE[40001]: Serialization failure: deadlock detected')
        );
    }

    public function test_a_menu_order_that_crashes_is_not_reported_as_an_empty_order(): void
    {
        $menu = Menu::create([
            'tenant_id' => null, 'name' => 'BS Restaurant', 'alias' => 'bs-resto',
            'currency' => 'HTG', 'ordering_enabled' => true,
        ]);
        $cat = $menu->categories()->create(['name' => 'Plats']);
        $item = $menu->items()->create([
            'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true,
        ]);

        $svc = \Mockery::mock(MenuOrderService::class);
        $svc->shouldReceive('placeOrder')->andThrow($this->dbPanne());
        $this->app->instance(MenuOrderService::class, $svc);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'customer_name' => 'Wilner', 'customer_phone' => '38112345',
        ]);
    }

    public function test_a_store_order_that_crashes_is_not_reported_as_an_empty_cart(): void
    {
        $store = Store::create([
            'tenant_id' => null, 'name' => 'Boutik', 'alias' => 'boutik',
            'currency' => 'HTG', 'is_published' => true,
        ]);
        $product = $store->products()->create(['name' => 'Rad', 'price' => 1000, 'is_available' => true]);

        $svc = \Mockery::mock(StoreOrderService::class);
        $svc->shouldReceive('placeOrder')->andThrow($this->dbPanne());
        $this->app->instance(StoreOrderService::class, $svc);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->postJson(route('tagtoa.store.order', $store->alias), [
            'items' => [['id' => $product->id, 'qty' => 1]],
            'customer_name' => 'Wilner', 'customer_phone' => '38112345',
        ]);
    }

    public function test_a_booking_that_crashes_is_not_reported_as_an_invalid_slot(): void
    {
        // « Créneau invalide. Choisissez une date à venir. » — c'est ce que
        // lisait le client quand l'ÉCRITURE échouait, alors que son créneau
        // était parfaitement bon.
        $page = BookingPage::create([
            'tenant_id' => null, 'name' => 'Salon', 'alias' => 'salon',
            'currency' => 'HTG', 'is_active' => true,
        ]);

        $svc = \Mockery::mock(BookingService::class);
        $svc->shouldReceive('placeBooking')->andThrow($this->dbPanne());
        $this->app->instance(BookingService::class, $svc);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->postJson(route('tagtoa.booking.reserve', $page->alias), [
            'customer_name' => 'Wilner', 'customer_phone' => '38112345',
            'starts_at' => now()->addDay()->format('Y-m-d H:i'),
        ]);
    }

    /** Les vraies règles métier, elles, répondent toujours 422 avec leur message. */
    public function test_a_genuine_business_rule_still_answers_422(): void
    {
        $menu = Menu::create([
            'tenant_id' => null, 'name' => 'BS Restaurant', 'alias' => 'bs-resto-2',
            'currency' => 'HTG', 'ordering_enabled' => true,
        ]);

        // Un article qui n'existe pas dans CE menu est ignoré par le
        // catalogue serveur : la commande se retrouve réellement vide, et
        // c'est bien la règle métier qui répond — pas la validation.
        $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => 999999, 'qty' => 1]],
            'customer_name' => 'Wilner', 'customer_phone' => '38112345',
        ])->assertStatus(422)->assertJson(['ok' => false]);
    }
}
