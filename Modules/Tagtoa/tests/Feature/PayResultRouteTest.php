<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — la page de résultat après un paiement en ligne
|--------------------------------------------------------------------------
| CheckoutController::return() redirige CHAQUE client vers /pay/result une
| fois la passerelle revenue (et les `cancel_url` de Stripe, PayPal et
| CoinPayments y pointent aussi). Or `/pay/{alias}` est déclarée AVANT
| `/pay/result` : Laravel retient la première route qui correspond, donc
| « result » était lu comme l'alias d'une page de paiement et finissait en
| 404 — le client venait de payer et lisait « page introuvable ».
|
| Pire : n'importe quel marchand pouvait créer une page d'alias « result »
| et devenir la page que TOUS les clients de TOUS les marchands voient après
| avoir payé.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\Tests\TestCase;

class PayResultRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_result_page_renders_after_a_payment(): void
    {
        $this->withSession(['pay_status' => 'paid'])
            ->get(route('tagtoa.pay.result'))
            ->assertOk();
    }

    public function test_a_merchant_page_aliased_result_cannot_take_over_that_page(): void
    {
        PaymentPage::create([
            'tenant_id' => null, 'alias' => 'result', 'title' => 'Hameçonnage',
            'default_currency' => 'HTG', 'is_active' => true,
        ]);

        $this->withSession(['pay_status' => 'paid'])
            ->get(route('tagtoa.pay.result'))
            ->assertOk()
            ->assertDontSee('Hameçonnage');
    }

    public function test_a_page_titled_result_never_gets_the_reserved_alias(): void
    {
        // Le slug de « Result » est « result » : l'alias généré tombait pile
        // sur la route fixe, sans que personne n'ait rien tapé de louche.
        $this->assertNotSame('result', PaymentPage::generateAlias('Result'));
        $this->assertSame('result-2', PaymentPage::generateAlias('Result'));
    }

    public function test_a_merchant_cannot_choose_the_reserved_alias_by_hand(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));

        $this->post(route('tagtoa.pay.dashboard.store'), ['title' => 'Ma page', 'alias' => 'result'])
            ->assertSessionHasErrors('alias');

        $this->assertSame(0, PaymentPage::count());
    }
}
