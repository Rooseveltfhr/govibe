<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — confirm() : tout d'un bloc, une seule fois
|--------------------------------------------------------------------------
| Avant : la transaction passait « payée », puis le grand livre, puis la
| commande, chacun sa propre écriture. Une panne au milieu laissait la
| transaction payée et la commande NON payée — et comme elle était déjà
| payée, plus aucun retour ni webhook ne réessayait : le client avait payé,
| le marchand ne le voyait jamais. Et le retour du client et le webhook de la
| passerelle, arrivant ensemble, appliquaient le paiement tous les deux.
|
| Une vraie concurrence ne se reproduit pas sous PHPUnit/SQLite : la course est
| simulée en faisant « payer » la transaction par une autre requête PENDANT
| l'appel à la passerelle — exactement la fenêtre qui existait.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\PaymentLedgerEntry;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Support\Gateways\GatewayDriver;
use Modules\Tagtoa\Tests\TestCase;

/** Une passerelle factice dont la réponse (et un effet de bord) se règlent. */
class FakeGatewayDriver implements GatewayDriver
{
    public $onVerify = null;

    public function __construct(public string $status = 'paid')
    {
    }

    public function createPayment(PayTransaction $txn): ?string
    {
        return null;
    }

    public function verify(PayTransaction $txn): string
    {
        if ($this->onVerify) {
            ($this->onVerify)($txn);
        }

        return $this->status;
    }
}

/** CheckoutService avec la passerelle factice, et un applyPaid qui peut tomber en panne. */
class TestableCheckout extends CheckoutService
{
    public int $applyCalls = 0;
    public int $failApplyTimes = 0;

    public function __construct(public FakeGatewayDriver $fake)
    {
        parent::__construct();
    }

    protected function driver(string $gateway): ?GatewayDriver
    {
        return $this->fake;
    }

    protected function applyPaid(PayTransaction $txn): void
    {
        $this->applyCalls++;
        if ($this->failApplyTimes > 0) {
            $this->failApplyTimes--;
            throw new \RuntimeException('panne simulée dans applyPaid');
        }
        parent::applyPaid($txn);
    }
}

class PayConfirmAtomicTest extends TestCase
{
    use RefreshDatabase;

    private function orderAndTxn(): array
    {
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose',
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);
        $order = app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);

        $txn = PayTransaction::create([
            'tenant_id' => 't-1', 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'menu', 'order_id' => $order->id, 'amount' => $order->total,
            'currency' => 'HTG', 'status' => PayTransaction::STATUS_PENDING,
        ]);

        return [$order, $txn];
    }

    private function ledgerRows(PayTransaction $txn): int
    {
        return PaymentLedgerEntry::where('payable_type', PayTransaction::class)->where('payable_id', $txn->id)->count();
    }

    /** Garde de non-régression : le chemin heureux marque tout, une seule fois. */
    public function test_a_confirmed_payment_marks_the_transaction_and_the_order_paid_once(): void
    {
        [$order, $txn] = $this->orderAndTxn();
        $svc = new TestableCheckout(new FakeGatewayDriver('paid'));

        $this->assertTrue($svc->confirm($txn->reference));
        $this->assertTrue($svc->confirm($txn->reference)); // webhook rejoué

        $this->assertSame('paid', $txn->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame('moncash', $order->fresh()->payment_method);
        $this->assertSame(1, $svc->applyCalls);
        $this->assertSame(1, $this->ledgerRows($txn));
    }

    public function test_a_failure_while_applying_rolls_everything_back_and_leaves_the_payment_retryable(): void
    {
        [$order, $txn] = $this->orderAndTxn();
        $svc = new TestableCheckout(new FakeGatewayDriver('paid'));
        $svc->failApplyTimes = 1;

        // Première tentative : applyPaid tombe en panne.
        $this->assertFalse($svc->confirm($txn->reference));

        $this->assertSame('pending', $txn->fresh()->status, 'La transaction ne doit pas rester « payée » sans commande payée.');
        $this->assertFalse($order->fresh()->isPaid());
        $this->assertSame(0, $this->ledgerRows($txn), 'Aucune écriture de grand livre orpheline.');
        $this->assertNull($txn->fresh()->paid_at);

        // Le prochain retour/webhook réessaie — et cette fois tout passe.
        $this->assertTrue($svc->confirm($txn->reference));
        $this->assertSame('paid', $txn->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, $this->ledgerRows($txn));
    }

    public function test_a_payment_applied_by_a_concurrent_request_is_not_applied_again(): void
    {
        [$order, $txn] = $this->orderAndTxn();
        $fake = new FakeGatewayDriver('paid');
        // Pendant que CETTE requête interroge la passerelle, l'autre (webhook)
        // finit son travail : transaction payée, commande payée.
        $fake->onVerify = function (PayTransaction $t) use ($order) {
            PayTransaction::whereKey($t->id)->update(['status' => 'paid', 'paid_at' => now()]);
            app(MenuOrderService::class)->markPaid(Order::find($order->id), 'moncash');
        };
        $svc = new TestableCheckout($fake);

        $this->assertTrue($svc->confirm($txn->reference));

        $this->assertSame(0, $svc->applyCalls, 'La perdante ne doit pas rejouer applyPaid.');
        $this->assertSame(0, $this->ledgerRows($txn), 'Ni écrire une seconde ligne de grand livre.');
    }

    public function test_a_late_failed_verdict_never_overwrites_a_payment(): void
    {
        [$order, $txn] = $this->orderAndTxn();
        $fake = new FakeGatewayDriver('failed');
        $fake->onVerify = fn (PayTransaction $t) => PayTransaction::whereKey($t->id)->update(['status' => 'paid', 'paid_at' => now()]);
        $svc = new TestableCheckout($fake);

        $svc->confirm($txn->reference);

        $this->assertSame('paid', $txn->fresh()->status);
        $this->assertSame(0, $this->ledgerRows($txn));
    }

    public function test_a_failed_payment_is_recorded_as_failed_once(): void
    {
        [, $txn] = $this->orderAndTxn();
        $svc = new TestableCheckout(new FakeGatewayDriver('failed'));

        $this->assertFalse($svc->confirm($txn->reference));
        $this->assertFalse($svc->confirm($txn->reference));

        $this->assertSame('failed', $txn->fresh()->status);
        $this->assertSame(1, $this->ledgerRows($txn), 'Un verdict « échoué » rejoué ne réécrit pas le grand livre.');
    }
}
