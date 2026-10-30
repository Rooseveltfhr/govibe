<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — un lien « Payer » ne mène jamais à une 404
|--------------------------------------------------------------------------
| Signalé : un client commande sur le menu, clique « Payer maintenant » et
| arrive sur tagtoa.com/pay/lib-xxxx… — « page introuvable ».
|
| `lib-…` est l'alias de la page TECHNIQUE « Moyens de paiement » (is_library,
| inactive) : /pay/{alias} la refuse exprès (elle ne doit jamais être
| publique). Mais les listes déroulantes « Page de paiement » du menu, de la
| boutique, de la réservation, des événements, des liens et du site la
| proposaient, et le marchand, n'ayant souvent que celle-là, la choisissait.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\Tests\TestCase;

class PayLinkServableTest extends TestCase
{
    use RefreshDatabase;

    private function library(): PaymentPage
    {
        return PaymentPage::create(['tenant_id' => 't-1', 'title' => 'Moyens de paiement', 'alias' => 'lib-abc123',
            'default_currency' => 'HTG', 'is_active' => false, 'is_library' => true]);
    }

    private function realPage(): PaymentPage
    {
        return PaymentPage::create(['tenant_id' => 't-1', 'title' => 'Ma page', 'alias' => 'ma-page',
            'default_currency' => 'HTG', 'is_active' => true, 'is_library' => false]);
    }

    private function menuWithItem(?PaymentPage $pay): array
    {
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose',
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true, 'pay_page_id' => $pay?->id]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return [$menu, $item];
    }

    private function order(Menu $menu, Item $item)
    {
        return $this->postJson(route('tagtoa.menu.order', $menu->alias), ['items' => [['id' => $item->id, 'qty' => 1]]]);
    }

    public function test_an_order_never_offers_the_technical_library_page_as_a_pay_link(): void
    {
        [$menu, $item] = $this->menuWithItem($this->library());

        $this->order($menu, $item)->assertOk()->assertJsonPath('pay_url', null);
    }

    /**
     * Garde de non-régression : un commerce qui a ses moyens de paiement obtient
     * un lien « Payer » — la page unifiée /pay/order, plus une page /pay/{alias}.
     */
    public function test_an_order_offers_the_unified_pay_page_when_the_merchant_has_a_method(): void
    {
        $lib = $this->library();
        \Modules\Tagtoa\App\Models\Pay\PaymentMethod::create([
            'payment_page_id' => $lib->id, 'tenant_id' => 't-1', 'type' => 'moncash',
            'account_number' => '3700 0000', 'is_active' => true,
        ]);
        [$menu, $item] = $this->menuWithItem($lib);

        $res = $this->order($menu, $item)->assertOk();
        $this->assertStringContainsString('/pay/order/menu/CMD-', $res->json('pay_url'));
    }

    public function test_the_menu_form_does_not_list_the_library_page(): void
    {
        $this->library();
        $this->realPage();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Ma page', $html);
        $this->assertStringNotContainsString('Moyens de paiement</option>', $html);
    }

    public function test_a_merchant_cannot_link_a_menu_to_the_library_page(): void
    {
        $lib = $this->library();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));

        $this->post(route('tagtoa.menu.dashboard.store'), [
            'name' => 'Nouveau', 'type' => 'restaurant', 'pay_page_id' => $lib->id,
        ])->assertSessionHasErrors('pay_page_id');
    }
}
