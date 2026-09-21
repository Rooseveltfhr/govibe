<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — « comment se porte le commerce aujourd'hui, comparé à hier ? »
|--------------------------------------------------------------------------
| L'accueil TAGTOA ne montrait qu'un compte de ressources (nombre de menus,
| de liens de paiement…) — jamais ce qu'un marchand veut vraiment savoir en
| ouvrant l'écran : est-ce que ça vend, aujourd'hui, mieux ou moins bien
| qu'hier ? OrderStatsService répond en lisant la colonne vertébrale (Order),
| déjà partagée par les quatre modules — sans aucune nouvelle requête dans
| POS, MENU ou EVENT.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Services\Order\OrderStatsService;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

class OrderStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    private function commande(array $attrs = []): Order
    {
        self::$seq++;

        return Order::create(array_merge([
            'tenant_id'      => 't-1',
            'channel'        => Channel::POS,
            'source_type'    => 'test',
            'source_id'      => self::$seq,
            'reference'      => 'TX-'.self::$seq,
            'total'          => 100,
            'currency'       => 'HTG',
            'status'         => OrderStatus::COMPLETED,
            'payment_status' => OrderStatus::PAID,
            'placed_at'      => now(),
        ], $attrs));
    }

    public function test_revenue_only_counts_orders_that_count_as_revenue(): void
    {
        $this->patron();
        $this->commande(['total' => 500, 'payment_status' => OrderStatus::PAID]);
        $this->commande(['total' => 300, 'payment_status' => OrderStatus::UNPAID]);
        $this->commande(['total' => 200, 'status' => OrderStatus::CANCELLED, 'payment_status' => OrderStatus::PAID]);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertSame(500.0, $today['revenue']['value']);
    }

    public function test_the_order_count_includes_everything_placed_today_regardless_of_payment(): void
    {
        $this->patron();
        $this->commande(['payment_status' => OrderStatus::PAID]);
        $this->commande(['payment_status' => OrderStatus::UNPAID]);
        $this->commande(['status' => OrderStatus::CANCELLED]);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertSame(3, $today['orders']['value']);
    }

    public function test_the_change_is_null_when_yesterday_had_nothing_to_compare_to(): void
    {
        $this->patron();
        $this->commande(['total' => 100]);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertNull($today['revenue']['change'], 'Aucune base hier : pas de pourcentage inventé.');
    }

    public function test_the_change_is_a_real_percentage_when_yesterday_had_activity(): void
    {
        $this->patron();
        $this->commande(['total' => 150, 'placed_at' => now()]);
        $this->commande(['total' => 100, 'placed_at' => now()->subDay()]);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertSame(50.0, $today['revenue']['change']);
    }

    public function test_the_same_customer_across_two_orders_is_counted_once(): void
    {
        $this->patron();
        $this->commande(['customer_phone' => '38112345']);
        $this->commande(['customer_phone' => '38112345']);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertSame(1, $today['customers']['value']);
    }

    public function test_walk_in_customers_without_any_identity_are_never_merged_into_one(): void
    {
        $this->patron();
        $this->commande(['customer_id' => null, 'customer_phone' => null]);
        $this->commande(['customer_id' => null, 'customer_phone' => null]);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertSame(0, $today['customers']['value'], 'Deux clients de passage anonymes ne sont pas le même client.');
    }

    public function test_a_neighbour_s_orders_never_count_in_this_tenant_s_stats(): void
    {
        $this->patron('t-1');
        Order::create([
            'tenant_id' => 't-2', 'channel' => Channel::POS, 'source_type' => 'test', 'source_id' => 999,
            'reference' => 'TX-999', 'total' => 5000, 'currency' => 'HTG',
            'status' => OrderStatus::COMPLETED, 'payment_status' => OrderStatus::PAID, 'placed_at' => now(),
        ]);
        $this->commande(['total' => 50]);

        $today = app(OrderStatsService::class)->todayVsYesterday();

        $this->assertSame(50.0, $today['revenue']['value']);
    }
}
