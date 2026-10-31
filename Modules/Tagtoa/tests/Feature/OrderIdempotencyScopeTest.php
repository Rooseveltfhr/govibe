<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — CS-7 : l'idempotence d'une commande vaut pour UN menu / UNE boutique
|--------------------------------------------------------------------------
| `client_uuid` était unique GLOBALEMENT et cherché sans filtre : un identifiant rejoué contre un autre
| commerce lui RENDAIT la commande d'autrui (référence, total, lien de suivi) ; deux commerces ne pouvaient
| pas partager un identifiant. Un doublon rapproché dans la boutique ne rendait qu'une erreur 500.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Store\Product;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Store\StoreOrderService;
use Modules\Tagtoa\Tests\TestCase;

class OrderIdempotencyScopeTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $alias, string $tenant): array
    {
        $m = Menu::create(['tenant_id' => $tenant, 'name' => 'Menu '.$alias, 'alias' => $alias, 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $c = Category::create(['menu_id' => $m->id, 'name' => 'Plats', 'is_active' => true]);
        $i = Item::create(['menu_id' => $m->id, 'category_id' => $c->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return [$m, $i];
    }

    private function store(string $alias, string $tenant): array
    {
        $s = Store::create(['tenant_id' => $tenant, 'name' => 'Boutik '.$alias, 'alias' => $alias, 'currency' => 'HTG', 'is_published' => true]);
        $p = Product::create(['store_id' => $s->id, 'name' => 'Savon', 'price' => 100, 'stock' => 50, 'is_available' => true]);

        return [$s, $p];
    }

    private function order(Menu $m, Item $i, string $uuid)
    {
        return app(MenuOrderService::class)->placeOrder($m, ['items' => [['id' => $i->id, 'qty' => 1]], 'client_uuid' => $uuid]);
    }

    /* ---------- menu ---------- */

    public function test_a_replay_in_the_same_menu_still_returns_the_same_order(): void
    {
        [$m, $i] = $this->menu('rose', 't-1');

        $a = $this->order($m, $i, 'mo-abc123');
        $b = $this->order($m, $i, 'mo-abc123');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $m->orders()->count());
    }

    public function test_the_same_identifier_in_another_menu_is_its_own_order_never_the_other_merchants(): void
    {
        [$m1, $i1] = $this->menu('rose', 't-1');
        [$m2, $i2] = $this->menu('paul', 't-2');

        $mine = $this->order($m1, $i1, 'mo-shared1');
        $theirs = $this->order($m2, $i2, 'mo-shared1');

        $this->assertNotSame($mine->id, $theirs->id);
        $this->assertNotSame($mine->reference, $theirs->reference, 'Jamais la référence du voisin.');
        $this->assertSame($m2->id, $theirs->menu_id);
        $this->assertSame('t-2', $theirs->tenant_id);
    }

    public function test_a_legacy_global_unique_index_never_leaks_nor_loses_an_order(): void
    {
        // Base où l'ancien index unique GLOBAL est encore là (migration pas encore passée).
        Schema::table('tagtoa_menu_orders', fn ($t) => $t->unique('client_uuid', 'legacy_uuid_unique'));
        [$m1, $i1] = $this->menu('rose', 't-1');
        [$m2, $i2] = $this->menu('paul', 't-2');
        $mine = $this->order($m1, $i1, 'mo-collide');

        $theirs = $this->order($m2, $i2, 'mo-collide');

        $this->assertNotSame($mine->id, $theirs->id, 'La commande de l\'autre commerce n\'est jamais rendue.');
        $this->assertSame($m2->id, $theirs->menu_id, 'Et la commande est enregistrée, pas perdue ni en erreur 500.');
    }

    /* ---------- boutique ---------- */

    public function test_store_replay_in_the_same_store_returns_the_same_order(): void
    {
        [$s, $p] = $this->store('savon', 't-1');
        $svc = app(StoreOrderService::class);

        $a = $svc->placeOrder($s, ['items' => [['id' => $p->id, 'qty' => 1]], 'client_uuid' => 'stk-aaa']);
        $b = $svc->placeOrder($s, ['items' => [['id' => $p->id, 'qty' => 1]], 'client_uuid' => 'stk-aaa']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(49.0, (float) $p->fresh()->stock, 'Le stock n\'est retiré qu\'une fois.');
    }

    public function test_store_same_identifier_in_another_store_is_its_own_order(): void
    {
        [$s1, $p1] = $this->store('savon', 't-1');
        [$s2, $p2] = $this->store('rhum', 't-2');
        $svc = app(StoreOrderService::class);

        $mine = $svc->placeOrder($s1, ['items' => [['id' => $p1->id, 'qty' => 1]], 'client_uuid' => 'stk-same']);
        $theirs = $svc->placeOrder($s2, ['items' => [['id' => $p2->id, 'qty' => 1]], 'client_uuid' => 'stk-same']);

        $this->assertNotSame($mine->id, $theirs->id);
        $this->assertSame($s2->id, $theirs->store_id);
    }

    public function test_store_racing_duplicate_is_recognised_instead_of_a_500(): void
    {
        [$s, $p] = $this->store('savon', 't-1');
        $svc = app(StoreOrderService::class);
        $first = $svc->placeOrder($s, ['items' => [['id' => $p->id, 'qty' => 1]], 'client_uuid' => 'stk-race']);

        // Le second « thread » a passé le contrôle préalable avant l'écriture du premier : on appelle
        // directement l'insertion, qui bute sur la contrainte unique.
        $m = new \ReflectionMethod($svc, 'insert');
        $m->setAccessible(true);
        try {
            $m->invoke($svc, $s, ['items' => [['id' => $p->id, 'qty' => 1]]], 'stk-race');
            $this->fail('La contrainte unique (store_id, client_uuid) devait refuser le doublon.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('client_uuid', $e->getMessage());
        }

        $this->assertSame(1, \Modules\Tagtoa\App\Models\Store\Order::where('store_id', $s->id)->count());
        $this->assertSame($first->id, \Modules\Tagtoa\App\Models\Store\Order::where('store_id', $s->id)->value('id'));
    }

    /* ---------- schéma ---------- */

    public function test_the_database_enforces_uniqueness_per_menu_and_allows_the_same_identifier_across_menus(): void
    {
        [$m1, $i1] = $this->menu('rose', 't-1');
        [$m2, $i2] = $this->menu('paul', 't-2');
        $this->order($m1, $i1, 'mo-db');
        $this->order($m2, $i2, 'mo-db');                       // autre menu : autorisé

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('tagtoa_menu_orders')->insert(['menu_id' => $m1->id, 'tenant_id' => 't-1', 'reference' => 'DUP-1', 'client_uuid' => 'mo-db',
            'status' => 'pending', 'payment_status' => 'unpaid', 'channel' => 'menu', 'currency' => 'HTG']);   // même menu : refusé
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $migration = require __DIR__.'/../../Database/migrations/2026_11_01_000180_scope_order_idempotency_to_menu_and_store.php';

        $migration->up();      // déjà passée : ne doit ni échouer ni dupliquer d'index
        $migration->up();

        $names = collect(DB::select('PRAGMA index_list("tagtoa_menu_orders")'))->pluck('name')->all();
        $this->assertContains('tagtoa_mo_menu_uuid_unique', $names);
        $this->assertNotContains('tagtoa_menu_orders_client_uuid_unique', $names, 'L\'ancien index global est retiré.');

        $migration->down();
        $names = collect(DB::select('PRAGMA index_list("tagtoa_menu_orders")'))->pluck('name')->all();
        $this->assertContains('tagtoa_menu_orders_client_uuid_unique', $names);
        $this->assertNotContains('tagtoa_mo_menu_uuid_unique', $names);

        $migration->up();
        $names = collect(DB::select('PRAGMA index_list("tagtoa_menu_orders")'))->pluck('name')->all();
        $this->assertContains('tagtoa_mo_menu_uuid_unique', $names);
    }
}
