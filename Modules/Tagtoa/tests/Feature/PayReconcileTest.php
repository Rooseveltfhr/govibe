<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — le filet des paiements « en attente » (CS-12)
|--------------------------------------------------------------------------
| Webhook perdu + client qui ne revient pas = argent reçu, commande impayée
| pour toujours. tagtoa:pay:reconcile re-vérifie auprès de la passerelle par le
| MÊME chemin que le webhook (confirmOrFail : verrou, atomique, idempotent).
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentLedgerEntry;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\Tests\TestCase;

class ReconcileFakeGateway implements \Modules\Tagtoa\App\Support\Gateways\GatewayDriver
{
    /** @var array<string,string|\Throwable> référence => statut ou exception */
    public static array $answers = [];
    public static array $calls = [];

    public function createPayment(PayTransaction $txn): ?string { return null; }

    public function verify(PayTransaction $txn): string
    {
        self::$calls[] = $txn->reference;
        $a = self::$answers[$txn->reference] ?? 'pending';
        if ($a instanceof \Throwable) {
            throw $a;
        }

        return $a;
    }
}

class ReconcileCheckout extends CheckoutService
{
    public int $applied = 0;

    protected function driver(string $gateway): ?\Modules\Tagtoa\App\Support\Gateways\GatewayDriver
    {
        return $gateway === 'unknown' ? null : new ReconcileFakeGateway();
    }

    protected function applyPaid(PayTransaction $txn): void
    {
        $this->applied++;
        parent::applyPaid($txn);
    }
}

class PayReconcileTest extends TestCase
{
    use RefreshDatabase;

    private ReconcileCheckout $checkout;
    private Menu $menu;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        ReconcileFakeGateway::$answers = [];
        ReconcileFakeGateway::$calls = [];
        $this->checkout = new ReconcileCheckout();
        $this->app->instance(CheckoutService::class, $this->checkout);

        $this->menu = Menu::create(['tenant_id' => 't-1', 'name' => 'BS', 'alias' => 'bs', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $this->menu->id, 'name' => 'P', 'is_active' => true]);
        $this->item = Item::create(['menu_id' => $this->menu->id, 'category_id' => $cat->id, 'name' => 'Pizza', 'price' => 500, 'is_available' => true]);
    }

    /** Transaction « en attente » créée il y a $ageMin minutes, dernière vérification il y a $checkedMin. */
    private function txn(int $ageMin, ?int $checkedMin = null, string $gateway = 'moncash', ?string $tenant = 't-1'): array
    {
        $order = app(MenuOrderService::class)->placeOrder($this->menu, ['items' => [['id' => $this->item->id, 'qty' => 1]]]);
        $txn = PayTransaction::create([
            'tenant_id' => $tenant, 'gateway' => $gateway, 'reference' => PayTransaction::generateReference(),
            'order_type' => 'menu', 'order_id' => $order->id, 'amount' => $order->total, 'currency' => 'HTG',
            'status' => PayTransaction::STATUS_PENDING,
        ]);
        $created = now()->subMinutes($ageMin);
        $checked = now()->subMinutes($checkedMin ?? $ageMin);
        PayTransaction::allTenants()->whereKey($txn->id)->toBase()->update(['created_at' => $created, 'updated_at' => $checked]);

        return [$order, $txn->fresh()];
    }

    private function run_(array $opts = []): void
    {
        $this->artisan('tagtoa:pay:reconcile', $opts)->assertExitCode(0);
    }

    public function test_a_paid_payment_with_no_webhook_is_settled_exactly_once(): void
    {
        [$order, $txn] = $this->txn(30);
        ReconcileFakeGateway::$answers[$txn->reference] = 'paid';

        $this->run_();

        $this->assertSame('paid', $txn->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, $this->checkout->applied);
        $ledger = PaymentLedgerEntry::where('payable_type', PayTransaction::class)->where('payable_id', $txn->id)->count();
        $this->assertSame(1, $ledger);

        // Rejouer, ou un webhook tardif : rien n'est appliqué une seconde fois.
        $this->run_();
        $this->checkout->confirm($txn->reference);
        $this->assertSame(1, $this->checkout->applied);
    }

    public function test_a_recent_payment_is_left_alone_so_the_customer_return_can_win(): void
    {
        [, $txn] = $this->txn(2);
        ReconcileFakeGateway::$answers[$txn->reference] = 'paid';

        $this->run_();

        $this->assertSame([], ReconcileFakeGateway::$calls);
        $this->assertSame('pending', $txn->fresh()->status);
    }

    public function test_the_minimum_age_option_delays_the_first_check(): void
    {
        [, $txn] = $this->txn(30);
        ReconcileFakeGateway::$answers[$txn->reference] = 'paid';

        $this->run_(['--min-age' => 45]);
        $this->assertSame([], ReconcileFakeGateway::$calls);

        $this->run_(['--min-age' => 20]);
        $this->assertSame([$txn->reference], ReconcileFakeGateway::$calls);
    }

    public function test_a_failed_payment_is_marked_failed_and_a_still_pending_one_stays_pending(): void
    {
        [, $failed] = $this->txn(30);
        [, $waiting] = $this->txn(30);
        ReconcileFakeGateway::$answers[$failed->reference] = 'failed';

        $this->run_();

        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('pending', $waiting->fresh()->status);
    }

    public function test_the_same_transaction_is_not_polled_more_often_than_its_cadence(): void
    {
        [, $txn] = $this->txn(30);

        $this->run_();
        $this->run_();   // juste après : trop tôt pour la même transaction
        $this->assertSame([$txn->reference], ReconcileFakeGateway::$calls);

        // Une transaction vérifiée il y a 11 min (< 1 h) est de nouveau éligible ;
        // une de 3 h vérifiée il y a 20 min ne l'est pas (cadence horaire).
        [, $due] = $this->txn(30, 11);
        [, $notYet] = $this->txn(180, 20);
        ReconcileFakeGateway::$calls = [];
        $this->run_();
        $this->assertContains($due->reference, ReconcileFakeGateway::$calls);
        $this->assertNotContains($notYet->reference, ReconcileFakeGateway::$calls);
    }

    public function test_abandoned_payments_older_than_the_window_are_not_polled_but_counted(): void
    {
        [, $old] = $this->txn(60 * 24 * 5);   // 5 jours

        $this->artisan('tagtoa:pay:reconcile')->expectsOutputToContain('abandonnées (> 72 h) 1')->assertExitCode(0);
        $this->assertNotContains($old->reference, ReconcileFakeGateway::$calls);
    }

    public function test_one_gateway_failure_does_not_stop_the_others_and_never_fails_the_command(): void
    {
        [, $boom] = $this->txn(40);
        [$orderOk, $ok] = $this->txn(30);
        ReconcileFakeGateway::$answers[$boom->reference] = new \RuntimeException('passerelle en panne');
        ReconcileFakeGateway::$answers[$ok->reference] = 'paid';

        $this->artisan('tagtoa:pay:reconcile')->expectsOutputToContain('erreurs 1')->assertExitCode(0);

        $this->assertSame('pending', $boom->fresh()->status);
        $this->assertSame('paid', $ok->fresh()->status);
        $this->assertTrue($orderOk->fresh()->isPaid());
    }

    public function test_it_covers_every_business_and_skips_unsupported_gateways_quietly(): void
    {
        [$o1, $a] = $this->txn(30, null, 'moncash', 't-1');
        [$o2, $b] = $this->txn(30, null, 'moncash', 't-2');
        [, $c] = $this->txn(30, null, 'unknown', 't-1');
        ReconcileFakeGateway::$answers[$a->reference] = 'paid';
        ReconcileFakeGateway::$answers[$b->reference] = 'paid';

        $this->run_();

        $this->assertSame('paid', $a->fresh()->status);
        $this->assertSame('paid', $b->fresh()->status);
        $this->assertSame('pending', $c->fresh()->status);
    }

    public function test_the_limit_caps_gateway_calls_per_run_least_recently_checked_first(): void
    {
        [, $older] = $this->txn(120, 120);
        [, $newer] = $this->txn(30, 30);
        [, $newest] = $this->txn(20, 20);

        $this->run_(['--limit' => 2]);

        $this->assertCount(2, ReconcileFakeGateway::$calls);
        $this->assertSame($older->reference, ReconcileFakeGateway::$calls[0], 'La moins récemment vérifiée passe d\'abord (pas de famine).');
    }

    public function test_bad_options_are_refused(): void
    {
        $this->artisan('tagtoa:pay:reconcile', ['--limit' => '0'])->assertExitCode(1);
        $this->artisan('tagtoa:pay:reconcile', ['--min-age' => '10m'])->assertExitCode(1);
    }

    public function test_the_provider_registers_the_schedule_and_can_be_switched_off(): void
    {
        $src = file_get_contents(__DIR__.'/../../app/Providers/TagtoaServiceProvider.php');
        $this->assertStringContainsString("->command('tagtoa:pay:reconcile')", $src);
        $this->assertStringContainsString('->withoutOverlapping(', $src);
        $this->assertStringContainsString("config('tagtoa.pay.reconcile', true)", $src);
    }
}
