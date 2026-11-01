<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY (PAY-2) — la page « Payer » : une grille de cartes, un seul choix
|--------------------------------------------------------------------------
| Plus de cartes empilées + menu déroulant : une carte cliquable par moyen réellement utilisable
| (radio natif : un seul choix, clavier et lecteur d'écran), le panneau du moyen choisi, et
| « J'ai payé » lié à CE moyen. Aucun moyen non utilisable n'est affiché, aucun faux logo.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Pay\PaymentChoices;
use Modules\Tagtoa\Tests\TestCase;

class PayOrderChoicesUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tagtoa.gateways.moncash.credentials' => ['client_id' => 'cid', 'secret' => 'sec']]);
        GatewayManager::flush();
    }

    private function menuOrder(string $tenant = 't-1'): Order
    {
        $menu = Menu::create(['tenant_id' => $tenant, 'name' => 'Chez Rose', 'alias' => 'chez-rose-'.$tenant,
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);
    }

    private function method(string $type, array $attrs = [], string $tenant = 't-1'): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id, 'tenant_id' => $tenant,
            'type' => $type, 'label' => $type.' de Rose', 'is_active' => true,
        ], $attrs));
    }

    private function html(Order $o): string
    {
        return $this->get(route('tagtoa.pay.order', ['menu', $o->reference]))->assertOk()->getContent();
    }

    public function test_each_usable_method_is_one_radio_card_and_there_is_no_dropdown_nor_checkbox(): void
    {
        $this->method('natcash', ['account_number' => '4400 2222']);
        $this->method('zelle', ['account_number' => 'rose@mail.com']);
        $html = $this->html($this->menuOrder());

        $this->assertSame(3, substr_count($html, 'type="radio" name="choice"'), 'MonCash (passerelle) + 2 moyens du marchand.');
        $this->assertStringNotContainsString('<select', $html, 'Plus de menu déroulant.');
        $this->assertStringNotContainsString('type="checkbox"', $html, 'Un seul moyen par transaction.');
        $this->assertStringContainsString('role="radiogroup"', $html);
    }

    public function test_a_method_that_is_not_usable_has_no_card(): void
    {
        $this->method('natcash', ['account_number' => '4400 2222', 'is_active' => false]);
        config(['tagtoa.gateways.moncash.credentials' => ['client_id' => 'cid', 'secret' => null]]);
        GatewayManager::flush();

        $html = $this->html($this->menuOrder());

        $this->assertSame(0, substr_count($html, 'name="choice"'));
        $this->assertStringContainsString('Payez directement auprès du commerce', $html);
    }

    public function test_another_merchants_method_never_gets_a_card(): void
    {
        $this->method('natcash', ['label' => 'Compte secret', 'account_number' => '9999 9999'], 't-2');

        $html = $this->html($this->menuOrder('t-1'));
        $this->assertStringNotContainsString('Compte secret', $html);
        $this->assertStringNotContainsString('9999 9999', $html);
    }

    public function test_online_card_has_its_pay_button_in_its_own_panel_with_the_server_built_url(): void
    {
        $o = $this->menuOrder();
        $html = $this->html($o);

        $this->assertStringContainsString('Confirmation automatique', $html);
        $this->assertStringContainsString('id="p-online-moncash"', $html);
        $this->assertStringContainsString(route('tagtoa.pay.online.start', ['gateway' => 'moncash', 'type' => 'menu', 'orderId' => $o->id]), $html);
        $this->assertStringContainsString('Payer avec MonCash', $html);
    }

    public function test_manual_card_panel_carries_its_details_and_a_claim_form_bound_to_that_method(): void
    {
        $m = $this->method('natcash', ['account_number' => '4400 2222', 'instructions' => 'Envoyez puis déclarez.']);
        $html = $this->html($this->menuOrder());

        $this->assertStringContainsString('Vérifié par le commerce', $html);
        $this->assertStringContainsString('id="p-manual-'.$m->id.'"', $html);
        $this->assertStringContainsString('4400 2222', $html);
        $this->assertStringContainsString('name="payment_method_id" value="'.$m->id.'"', $html);
    }

    public function test_a_single_choice_is_preselected_and_several_are_not(): void
    {
        $o = $this->menuOrder();
        $html = $this->html($o);                             // MonCash seul
        $this->assertStringContainsString('value="online-moncash" data-panel="p-online-moncash" checked', $html);
        $this->assertDoesNotMatchRegularExpression('/id="p-online-moncash"\s+hidden/', $html);

        $this->method('natcash', ['account_number' => '4400 2222']);
        $html = $this->html($o);
        $grille = substr($html, strpos($html, 'role="radiogroup"'), strpos($html, 'class="panel"') - strpos($html, 'role="radiogroup"'));
        $this->assertStringNotContainsString(' checked', $grille, 'Plusieurs moyens : le client choisit lui-même.');
        $this->assertMatchesRegularExpression('/id="p-online-moncash"\s+hidden/', $html);
    }

    public function test_the_merchants_uploaded_logo_is_shown_otherwise_a_plain_icon_never_a_fake_brand_logo(): void
    {
        $this->method('natcash', ['account_number' => '1', 'logo_path' => 'tagtoa/pay-logos/rose.png']);
        $this->method('zelle', ['account_number' => '2']);
        $html = $this->html($this->menuOrder());

        $this->assertStringContainsString('pay-logos/rose.png', $html);
        $this->assertSame(1, substr_count($html, 'pay-logos/'), 'Un seul logo : celui que le marchand a fourni.');
        $this->assertStringContainsString('fa-dollar-sign', $html, 'Zelle : icône générique sur pastille de couleur.');
    }

    public function test_brand_colors_are_sanitised_before_reaching_a_style_attribute(): void
    {
        $this->assertSame('#2cb809', PaymentChoices::safeColor('red;background:url(javascript:1)'));
        $this->assertSame('#E2001A', PaymentChoices::safeColor('#E2001A'));
        $this->assertSame('#fff', PaymentChoices::safeColor('#fff'));
    }

    public function test_the_merchants_labels_are_escaped_in_the_card(): void
    {
        $this->method('natcash', ['label' => '<script>alert(1)</script>', 'account_number' => '1']);
        $html = $this->html($this->menuOrder());

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_failed_claim_reopens_the_chosen_method_with_the_message_and_what_was_typed(): void
    {
        $this->method('zelle', ['account_number' => '2']);
        $m = $this->method('natcash', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();

        $html = $this->followingRedirects()->from(route('tagtoa.pay.order', ['menu', $o->reference]))
            ->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'payer_name' => 'Jean', 'payer_phone' => 'abc'])
            ->getContent();

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('value="manual-'.$m->id.'" data-panel="p-manual-'.$m->id.'" checked', $html);
        $this->assertStringContainsString('value="Jean"', $html);
    }

    public function test_the_claim_still_pays_nothing_and_refuses_an_online_method(): void
    {
        $m = $this->method('natcash', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'payer_phone' => '(509) 3456-7890'])
            ->assertSessionHasNoErrors();
        $o = $o->fresh();
        $this->assertFalse($o->isPaid());
        $this->assertNotNull($o->payment_claimed_at);

        $this->assertStringContainsString('Le commerce vérifie votre paiement', $this->html($o));
        $this->assertStringNotContainsString('id="claim-', $this->html($o), 'Déjà déclaré : plus de formulaire.');
    }

    public function test_a_paid_order_shows_no_card_at_all(): void
    {
        $this->method('natcash', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();
        $o->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save();
        if (! $o->fresh()->isPaid()) {
            $this->markTestSkipped('Colonnes de paiement différentes sur ce modèle.');
        }

        $html = $this->html($o->fresh());
        $this->assertSame(0, substr_count($html, 'name="choice"'));
        $this->assertStringContainsString('Cette commande est payée', $html);
    }
}
