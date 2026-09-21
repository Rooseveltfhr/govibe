<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — « Commander via Agent IA », sans LLM (aucun n'est branché
| dans TAGTOA aujourd'hui). L'endpoint public ne fait que SUGGÉRER des
| correspondances (mots-clés locaux, OrderChatParser) — il ne crée jamais
| de commande, ne fuit jamais un article d'un autre menu, et ignore les
| articles indisponibles comme s'ils n'existaient pas.
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\Tests\TestCase;

class MenuAgentOrderTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create(['tenant_id' => $tenantId, 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG', 'is_active' => true]);
    }

    private function item(Menu $menu, array $attrs = []): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 250, 'is_available' => true,
        ], $attrs));
    }

    public function test_it_matches_a_free_text_message_to_a_menu_item(): void
    {
        $menu = $this->menu();
        $this->item($menu, ['name' => 'Griot']);

        $json = $this->postJson(route('tagtoa.menu.agent', $menu->alias), ['message' => '2 griot'])
            ->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame(1, count($json['matches']));
        $this->assertSame(2, $json['matches'][0]['qty']);
        $this->assertSame([], $json['unmatched']);
    }

    public function test_it_never_creates_an_order(): void
    {
        $menu = $this->menu();
        $this->item($menu, ['name' => 'Griot']);

        $this->postJson(route('tagtoa.menu.agent', $menu->alias), ['message' => '2 griot']);

        $this->assertSame(0, Order::count());
    }

    public function test_it_ignores_an_unavailable_item(): void
    {
        $menu = $this->menu();
        $this->item($menu, ['name' => 'Griot', 'is_available' => false]);

        $json = $this->postJson(route('tagtoa.menu.agent', $menu->alias), ['message' => 'griot'])->json();

        $this->assertSame([], $json['matches']);
        $this->assertSame(['griot'], $json['unmatched']);
    }

    public function test_it_never_suggests_an_item_from_another_menu(): void
    {
        $mine = $this->menu('t-1');
        $other = $this->menu('t-2');
        $this->item($other, ['name' => 'Griot exclusif']);

        $json = $this->postJson(route('tagtoa.menu.agent', $mine->alias), ['message' => 'griot exclusif'])->json();

        $this->assertSame([], $json['matches']);
    }

    public function test_an_inactive_menu_has_no_agent_endpoint(): void
    {
        $menu = $this->menu();
        $menu->update(['is_active' => false]);

        $this->postJson(route('tagtoa.menu.agent', $menu->alias), ['message' => 'griot'])->assertNotFound();
    }

    public function test_a_missing_message_is_rejected(): void
    {
        $menu = $this->menu();

        $this->postJson(route('tagtoa.menu.agent', $menu->alias), [])->assertStatus(422);
    }

    public function test_the_response_never_includes_a_price(): void
    {
        // Le prix doit TOUJOURS venir de la carte affichée côté client,
        // jamais de cette réponse — sinon un client pourrait falsifier le
        // prix en manipulant la requête /agent.
        $menu = $this->menu();
        $this->item($menu, ['name' => 'Griot', 'price' => 999]);

        $json = $this->postJson(route('tagtoa.menu.agent', $menu->alias), ['message' => 'griot'])->json();

        $this->assertArrayNotHasKey('price', $json['matches'][0]);
    }
}
