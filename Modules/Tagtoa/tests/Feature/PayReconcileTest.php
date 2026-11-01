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
use Modules\Tagtoa\App\Console\ReconcilePaymentsCommand;
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
    /** @var callable|null */
    public static $onVerify = null;

    public function createPayment(PayTransaction $txn): ?string { return null; }

    public function verify(PayTransaction $txn): string
    {
        self::$calls[] = $txn->reference;
        if (self::$onVerify) {
            (self::$onVerify)($txn);
        }
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
        ReconcileFakeGateway::$onVerify = null;
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

    public function test_the_net_never_writes_failed_a_failure_verdict_is_only_counted(): void
    {
        [$order, $partial] = $this->txn(30);
        [, $waiting] = $this->txn(30);
        ReconcileFakeGateway::$answers[$partial->reference] = 'failed';   // ex. paiement partiel, ancienne session…

        $this->artisan('tagtoa:pay:reconcile')->expectsOutputToContain("verdict d'échec (laissé en attente) 1")->assertExitCode(0);

        $this->assertSame('pending', $partial->fresh()->status, 'Un verdict « échec » ne doit jamais devenir définitif par le filet.');
        $this->assertSame('pending', $waiting->fresh()->status);
        $this->assertFalse($order->fresh()->isPaid());

        // Un webhook / retour client tardif peut ENCORE le régler (rien n'a été fermé).
        ReconcileFakeGateway::$answers[$partial->reference] = 'paid';
        $this->assertTrue($this->checkout->confirm($partial->reference));
        $this->assertSame('paid', $partial->fresh()->status);
    }

    public function test_an_already_failed_transaction_is_never_polled_again(): void
    {
        [, $txn] = $this->txn(30);
        PayTransaction::allTenants()->whereKey($txn->id)->toBase()->update(['status' => 'failed']);

        $this->run_();

        $this->assertSame([], ReconcileFakeGateway::$calls);
        $this->assertSame('failed', $txn->fresh()->status);
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

        [, $fresh] = $this->txn(30);   // une transaction éligible prouve que la liste d'appels n'est pas vide pour rien
        $this->artisan('tagtoa:pay:reconcile')->expectsOutputToContain('abandonnées (> 72 h) 1')->assertExitCode(0);
        $this->assertSame([$fresh->reference], ReconcileFakeGateway::$calls);
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

    public function test_it_polls_every_business_not_only_the_ambient_one(): void
    {
        // Un commerce ambiant (t-1) : sans allTenants() la portée cacherait la transaction de t-2.
        $this->be(new \Illuminate\Auth\GenericUser(['id' => 9, 'tenant_id' => 't-1']));
        \Modules\Tagtoa\App\Support\Tenant::flush();

        [, $a] = $this->txn(30, null, 'moncash', 't-1');
        [, $b] = $this->txn(30, null, 'moncash', 't-2');
        [, $c] = $this->txn(30, null, 'unknown', 't-1');

        $this->run_();

        $this->assertEqualsCanonicalizing([$a->reference, $b->reference], ReconcileFakeGateway::$calls);
        $this->assertSame('pending', PayTransaction::allTenants()->find($c->id)->status, 'Passerelle inconnue : ignorée sans bruit.');
    }

    public function test_every_business_is_settled_in_console_context(): void
    {
        [, $a] = $this->txn(30, null, 'moncash', 't-1');
        ReconcileFakeGateway::$answers[$a->reference] = 'paid';

        $this->run_();

        $this->assertSame('paid', PayTransaction::allTenants()->find($a->id)->status);
        $this->assertSame('t-1', PayTransaction::allTenants()->find($a->id)->tenant_id);
    }

    public function test_a_payment_settled_by_a_concurrent_request_during_the_gateway_call_is_not_applied_twice(): void
    {
        [$order, $txn] = $this->txn(30);
        ReconcileFakeGateway::$answers[$txn->reference] = 'paid';
        ReconcileFakeGateway::$onVerify = function (PayTransaction $t) use ($order) {
            // Pendant l'appel passerelle du filet, le webhook finit son travail.
            PayTransaction::allTenants()->whereKey($t->id)->toBase()->update(['status' => 'paid', 'paid_at' => now()]);
            app(MenuOrderService::class)->markPaid(\Modules\Tagtoa\App\Models\Menu\Order::find($order->id), 'moncash');
        };

        $this->run_();

        $this->assertSame(0, $this->checkout->applied, 'Le filet ne ré-applique pas un paiement déjà réglé par une autre requête.');
        $this->assertSame('paid', $txn->fresh()->status);
    }

    public function test_a_cancelled_event_order_is_skipped_before_asking_the_gateway(): void
    {
        $event = \Modules\Tagtoa\App\Models\Event\Event::create([
            'tenant_id' => 't-1', 'title' => 'Gala', 'alias' => 'gala', 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ]);
        $type = \Modules\Tagtoa\App\Models\Event\TicketType::create(['event_id' => $event->id, 'name' => 'Std', 'price' => 500, 'currency' => 'HTG', 'is_active' => true, 'quota' => 10]);
        $eventOrder = app(\Modules\Tagtoa\App\Services\Event\TicketService::class)
            ->createOrder($event, [['ticket_type_id' => $type->id, 'qty' => 1]], ['name' => 'Marie', 'phone' => '+50937000000']);
        $eventOrder->update(['status' => \Modules\Tagtoa\App\Models\Event\Order::STATUS_CANCELLED]);

        $txn = PayTransaction::create([
            'tenant_id' => 't-1', 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'event', 'order_id' => $eventOrder->id, 'amount' => 500, 'currency' => 'HTG', 'status' => 'pending',
        ]);
        PayTransaction::allTenants()->whereKey($txn->id)->toBase()->update(['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);
        ReconcileFakeGateway::$answers[$txn->reference] = 'paid';

        $this->artisan('tagtoa:pay:reconcile')->expectsOutputToContain('ignorées 1')->assertExitCode(0);

        $this->assertSame([], ReconcileFakeGateway::$calls, 'Pas d\'appel passerelle : verify() peut capturer un paiement différé.');
        $this->assertSame('pending', $txn->fresh()->status);
        $this->assertSame(\Modules\Tagtoa\App\Models\Event\Order::STATUS_CANCELLED, $eventOrder->fresh()->status);
    }

    public function test_max_age_hours_also_bounds_the_one_to_twenty_four_hour_window(): void
    {
        [, $fiveHours] = $this->txn(60 * 5);
        [, $recent] = $this->txn(30);

        $this->run_(['--max-age-hours' => 2]);

        $this->assertSame([$recent->reference], ReconcileFakeGateway::$calls);
    }

    public function test_the_time_budget_stops_the_run_and_defers_the_rest(): void
    {
        $this->txn(30);
        $this->txn(31);
        ReconcileFakeGateway::$onVerify = fn () => usleep(1_200_000);   // passerelle lente

        $this->artisan('tagtoa:pay:reconcile', ['--budget' => 1])->expectsOutputToContain('reportées (temps) 1')->assertExitCode(0);

        $this->assertCount(1, ReconcileFakeGateway::$calls);
    }

    public function test_the_kill_switch_stops_everything(): void
    {
        [, $txn] = $this->txn(30);
        config(['tagtoa.pay.reconcile' => false]);

        $this->artisan('tagtoa:pay:reconcile')->expectsOutputToContain('désactivée')->assertExitCode(0);

        $this->assertSame([], ReconcileFakeGateway::$calls);
    }

    public function test_gateway_errors_are_logged_without_sql_bindings_or_personal_data(): void
    {
        [, $txn] = $this->txn(30);
        $secret = '+50937000000';
        ReconcileFakeGateway::$answers[$txn->reference] = new \Illuminate\Database\QueryException(
            'sqlite', 'insert into x (phone) values (?)', [$secret], new \Exception('boom '.$secret)
        );
        \Illuminate\Support\Facades\Log::spy();

        $this->run_();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(function ($msg, $ctx) use ($secret) {
            return $msg === 'tagtoa.reconcile.error' && ! str_contains(json_encode($ctx), $secret) && $ctx['error'] === \Illuminate\Database\QueryException::class;
        })->once();
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

    public function test_the_schedule_event_is_real_overlap_safe_and_in_the_background(): void
    {
        $schedule = new \Illuminate\Console\Scheduling\Schedule();
        ReconcilePaymentsCommand::schedule($schedule);

        $events = $schedule->events();
        $this->assertCount(1, $events);
        $this->assertStringContainsString('tagtoa:pay:reconcile', $events[0]->command);
        $this->assertSame('*/10 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertTrue($events[0]->runInBackground);
    }

    public function test_the_provider_calls_that_registration_behind_the_switch(): void
    {
        $src = file_get_contents(__DIR__.'/../../app/Providers/TagtoaServiceProvider.php');
        $this->assertStringContainsString('ReconcilePaymentsCommand::schedule(', $src);
        $this->assertStringContainsString("config('tagtoa.pay.reconcile', true)", $src);
    }
}
