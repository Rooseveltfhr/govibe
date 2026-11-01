<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY (PAY-3) — devises acceptées par moyen + aperçu client
|--------------------------------------------------------------------------
| Un moyen manuel peut être limité à certaines devises (Zelle : USD seulement). Vide = toutes,
| donc rien ne change pour l'existant. Le serveur filtre l'affichage ET la déclaration « J'ai
| payé » ; un moyen manuel activé sans rien à montrer est refusé ; l'aperçu client utilise le
| même code que la page « Payer ».
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class PayMethodCurrenciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
    }

    private function patron(string $tenant = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
        Tenant::flush();
    }

    private function menuOrder(string $currency = 'HTG', string $tenant = 't-1'): Order
    {
        $menu = Menu::create(['tenant_id' => $tenant, 'name' => 'Chez Rose', 'alias' => 'cr-'.$currency.$tenant,
            'currency' => $currency, 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);
    }

    private function method(string $type, ?array $currencies, array $attrs = [], string $tenant = 't-1'): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id, 'tenant_id' => $tenant,
            'type' => $type, 'label' => $type.' de Rose', 'account_number' => 'N-'.$type, 'is_active' => true,
            'currencies' => $currencies,
        ], $attrs));
    }

    private function save(array $rows)
    {
        return $this->put(route('tagtoa.pay.methods.update'), ['methods' => $rows]);
    }

    /* ---------- le filtre ---------- */

    public function test_a_method_without_currency_list_accepts_every_currency(): void
    {
        $m = $this->method('natcash', null);
        $this->assertTrue($m->acceptsCurrency('HTG'));
        $this->assertTrue($m->acceptsCurrency('USD'));
        $this->assertTrue($this->method('zelle', [])->acceptsCurrency('EUR'), 'Liste vide = toutes.');
    }

    public function test_a_restricted_method_accepts_only_its_currencies_case_insensitively(): void
    {
        $m = $this->method('zelle', ['USD']);
        $this->assertTrue($m->acceptsCurrency('USD'));
        $this->assertTrue($m->acceptsCurrency('usd'));
        $this->assertFalse($m->acceptsCurrency('HTG'));
    }

    public function test_the_pay_page_only_shows_methods_that_accept_the_order_currency(): void
    {
        $this->method('zelle', ['USD']);
        $this->method('natcash', ['HTG']);
        $this->method('bnc', null);

        $htg = $this->get(route('tagtoa.pay.order', ['menu', $this->menuOrder('HTG')->reference]))->assertOk()->getContent();
        $this->assertStringContainsString('N-natcash', $htg);
        $this->assertStringContainsString('N-bnc', $htg);
        $this->assertStringNotContainsString('N-zelle', $htg, 'Zelle est limité à USD.');
    }

    public function test_the_claim_is_refused_for_a_method_that_does_not_accept_the_currency(): void
    {
        $zelle = $this->method('zelle', ['USD']);
        $o = $this->menuOrder('HTG');

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $zelle->id])
            ->assertSessionHasErrors('payment_method_id');
        $this->assertNull($o->fresh()->payment_claim, 'Le serveur revérifie : le formulaire n\'est pas une preuve.');
    }

    public function test_when_no_method_accepts_the_currency_the_page_says_to_pay_at_the_shop(): void
    {
        $this->method('zelle', ['USD']);
        $o = $this->menuOrder('HTG');

        $this->get(route('tagtoa.pay.order', ['menu', $o->reference]))->assertOk()->assertSee('Payez directement auprès du commerce');
        $this->assertNull(\Modules\Tagtoa\App\Http\Controllers\Pay\OrderPayController::urlFor('menu', $o));
    }

    /* ---------- réglages marchand ---------- */

    public function test_the_merchant_saves_the_accepted_currencies_and_the_form_shows_them_ticked(): void
    {
        $this->patron();
        $this->save(['zelle' => ['enabled' => '1', 'account_number' => 'rose@mail.com', 'currencies' => ['usd', 'USD', 'EUR']]])
            ->assertSessionHasNoErrors();

        $m = PaymentMethod::where('type', 'zelle')->sole();
        $this->assertEqualsCanonicalizing(['USD', 'EUR'], $m->currencies, 'Majuscules, sans doublon.');

        $html = $this->get(route('tagtoa.pay.methods'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="methods\[zelle\]\[currencies\]\[\]" value="USD" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="methods\[zelle\]\[currencies\]\[\]" value="HTG" checked/', $html);
    }

    public function test_unticking_everything_restores_all_currencies(): void
    {
        $this->patron();
        $this->method('zelle', ['USD']);

        $this->save(['zelle' => ['enabled' => '1', 'account_number' => 'N-zelle']])->assertSessionHasNoErrors();
        $this->assertNull(PaymentMethod::where('type', 'zelle')->sole()->currencies);
    }

    /** @dataProvider badCurrencies */
    public function test_an_unknown_or_malformed_currency_is_refused(array $currencies): void
    {
        $this->patron();
        $this->save(['zelle' => ['enabled' => '1', 'account_number' => 'x', 'currencies' => $currencies]])
            ->assertSessionHasErrors();
        $this->assertSame(0, PaymentMethod::count());
    }

    public static function badCurrencies(): array
    {
        return [['inconnue' => ['XXX']], ['balise' => ['<b>']], ['imbriqué' => [['USD']]]];
    }

    public function test_a_manual_method_enabled_with_nothing_to_show_is_refused_but_the_others_are_saved(): void
    {
        $this->patron();
        $this->save([
            'zelle'   => ['enabled' => '1'],                                   // rien à montrer
            'natcash' => ['enabled' => '1', 'account_number' => '4400 2222'],   // valide
        ])->assertSessionHasErrors('methods');

        $this->assertSame(1, PaymentMethod::count());
        $this->assertSame('natcash', PaymentMethod::sole()->type);
    }

    public function test_an_existing_method_with_its_details_stays_enabled_when_saved_again_without_retyping(): void
    {
        $this->patron();
        $this->method('natcash', null, ['account_number' => '4400 2222']);

        $this->save(['natcash' => ['enabled' => '1', 'account_number' => '4400 2222']])->assertSessionHasNoErrors();
        $this->assertTrue((bool) PaymentMethod::sole()->is_active);
    }

    public function test_another_merchants_currencies_are_never_touched(): void
    {
        $this->method('zelle', ['EUR'], [], 't-2');
        $this->patron('t-1');
        $this->save(['zelle' => ['enabled' => '1', 'account_number' => 'mine', 'currencies' => ['USD']]])->assertSessionHasNoErrors();

        $this->assertSame(['EUR'], PaymentMethod::allTenants()->where('tenant_id', 't-2')->sole()->currencies);
        $this->assertSame(['USD'], PaymentMethod::allTenants()->where('tenant_id', 't-1')->sole()->currencies);
    }

    /* ---------- aperçu client ---------- */

    public function test_the_preview_shows_what_a_customer_paying_in_that_currency_would_see(): void
    {
        $this->patron();
        $this->method('zelle', ['USD'], ['label' => 'Zelle Rose']);
        $this->method('natcash', ['HTG'], ['label' => 'NatCash Rose']);

        $usd = $this->get(route('tagtoa.pay.methods', ['preview' => 'USD']))->assertOk()->getContent();
        $apercu = substr($usd, strpos($usd, 'Aperçu client'), 1500);
        $this->assertStringContainsString('Zelle Rose', $apercu);
        $this->assertStringNotContainsString('NatCash Rose', $apercu);

        $htg = $this->get(route('tagtoa.pay.methods', ['preview' => 'HTG']))->assertOk()->getContent();
        $apercu = substr($htg, strpos($htg, 'Aperçu client'), 1500);
        $this->assertStringContainsString('NatCash Rose', $apercu);
        $this->assertStringNotContainsString('Zelle Rose', $apercu);
    }

    public function test_the_preview_warns_when_nothing_is_offered_and_ignores_an_unknown_currency(): void
    {
        $this->patron();
        $this->method('zelle', ['USD']);

        $this->get(route('tagtoa.pay.methods', ['preview' => 'HTG']))->assertOk()->assertSee('Aucun moyen de paiement');
        $this->get(route('tagtoa.pay.methods', ['preview' => '<script>']))->assertOk()->assertDontSee('Un client qui paie en');
    }

    public function test_the_preview_never_shows_another_merchants_methods(): void
    {
        $this->method('zelle', null, ['label' => 'Zelle secret'], 't-2');
        $this->patron('t-1');

        $this->get(route('tagtoa.pay.methods', ['preview' => 'USD']))->assertOk()->assertDontSee('Zelle secret');
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000186_add_currencies_to_payment_methods.php';
        $mig->up();
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_payment_methods', 'currencies'));
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_payment_methods', 'currencies'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_payment_methods', 'currencies'));
    }
}
