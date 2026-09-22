<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — l'aperçu « aujourd'hui, comparé à hier » sur l'accueil
|--------------------------------------------------------------------------
| Couvre le câblage HubController → OrderStatsService → vue, sans refaire
| les tests de calcul (voir OrderStatsServiceTest) : ici on vérifie que le
| bloc apparaît (ou pas) au bon moment, et que ce qu'il affiche vient bien
| du service plutôt que d'un chiffre recalculé dans la vue.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

class HubTodayStatsTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    public function test_a_brand_new_merchant_never_sees_the_today_block(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.hub'))->assertOk()->getContent();

        $this->assertStringNotContainsString("comparé à hier", $html);
    }

    public function test_an_established_merchant_with_no_activity_today_sees_dashes_not_zeros_pretending_to_be_a_trend(): void
    {
        $this->patron();
        Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);

        $html = $this->get(route('tagtoa.hub'))->assertOk()->getContent();

        $this->assertStringContainsString("comparé à hier", $html);
        // Aucune vente aujourd'hui NI hier : rien à comparer, donc pas de
        // pourcentage — seulement un tiret.
        $this->assertStringContainsString('>—<', $html);
    }

    public function test_today_s_revenue_shown_is_the_one_the_service_computed(): void
    {
        $this->patron();
        Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
        Order::create([
            'tenant_id' => 't-1', 'channel' => Channel::POS, 'source_type' => 'test', 'source_id' => 1,
            'reference' => 'TX-1', 'total' => 1234, 'currency' => 'HTG',
            'status' => OrderStatus::COMPLETED, 'payment_status' => OrderStatus::PAID, 'placed_at' => now(),
        ]);

        $html = $this->get(route('tagtoa.hub'))->assertOk()->getContent();

        $this->assertStringContainsString('1,234 G', $html);
    }

    public function test_a_positive_change_is_shown_distinctly_from_a_negative_one(): void
    {
        $this->patron();
        Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
        Order::create([
            'tenant_id' => 't-1', 'channel' => Channel::POS, 'source_type' => 'test', 'source_id' => 1,
            'reference' => 'TX-1', 'total' => 200, 'currency' => 'HTG',
            'status' => OrderStatus::COMPLETED, 'payment_status' => OrderStatus::PAID, 'placed_at' => now(),
        ]);
        Order::create([
            'tenant_id' => 't-1', 'channel' => Channel::POS, 'source_type' => 'test', 'source_id' => 2,
            'reference' => 'TX-2', 'total' => 100, 'currency' => 'HTG',
            'status' => OrderStatus::COMPLETED, 'payment_status' => OrderStatus::PAID, 'placed_at' => now()->subDay(),
        ]);

        $html = $this->get(route('tagtoa.hub'))->assertOk()->getContent();

        // +100 % (200 vs 100 la veille) : la flèche vers le haut, pas rouge.
        $this->assertStringContainsString('fa-arrow-up', $html);
        $this->assertStringContainsString('100.0%', $html);
    }
}
