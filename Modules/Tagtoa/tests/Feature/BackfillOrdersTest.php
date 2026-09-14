<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Rejouer l'historique sur la colonne vertébrale.
 *
 * Le jour du déploiement, les ventes déjà encaissées — parfois des mois — ne
 * sont pas dans la nouvelle table. Sans cette commande, le marchand ouvrirait
 * un rapport « tous canaux » affichant zéro et conclurait que TAGTOA a perdu
 * ses chiffres.
 */
class BackfillOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    /** Une vente telle qu'elle existait AVANT la colonne vertébrale. */
    private function venteAncienne(array $attrs = [], string $tenantId = 't-1'): Sale
    {
        return Sale::create(array_merge([
            'terminal_id' => $this->caisse($tenantId)->id,
            'reference'   => 'TGP-'.strtoupper(bin2hex(random_bytes(3))),
            'subtotal'    => 200, 'discount' => 0, 'total' => 200,
            'currency'    => 'HTG', 'status' => 1,
            'sold_at'     => now()->subMonths(2),
        ], $attrs));
    }

    public function test_past_sales_are_written_onto_the_spine(): void
    {
        $vente = $this->venteAncienne();
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        $this->artisan('tagtoa:orders:backfill')->assertExitCode(0);

        $ligne = Order::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(Channel::POS, $ligne->channel);
        $this->assertSame($vente->reference, $ligne->reference);
        $this->assertEquals(200.0, (float) $ligne->total);
        $this->assertSame(OrderStatus::PAID, $ligne->payment_status);
        $this->assertSame('t-1', $ligne->tenant_id, 'Le commerce vient de la caisse, pas de la session.');
    }

    public function test_the_sale_date_is_kept_not_todays(): void
    {
        // Une vente d'il y a deux mois rangée à aujourd'hui fausserait tous les
        // rapports par période dès le premier jour.
        $vente = $this->venteAncienne();

        $this->artisan('tagtoa:orders:backfill');

        $this->assertSame(
            $vente->sold_at->toDateString(),
            Order::withoutGlobalScopes()->firstOrFail()->placed_at->toDateString()
        );
    }

    public function test_running_it_twice_never_doubles_the_takings(): void
    {
        // La commande doit pouvoir être relancée après une interruption.
        $this->venteAncienne();

        $this->artisan('tagtoa:orders:backfill');
        $this->artisan('tagtoa:orders:backfill');

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertEquals(200.0, (float) Order::withoutGlobalScopes()->sum('total'));
    }

    public function test_a_cancelled_sale_never_becomes_revenue(): void
    {
        $this->venteAncienne(['status' => 0]);

        $this->artisan('tagtoa:orders:backfill');

        $ligne = Order::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(OrderStatus::CANCELLED, $ligne->status);
        $this->assertFalse($ligne->countsAsRevenue());
    }

    public function test_each_shop_keeps_its_own_history(): void
    {
        $this->venteAncienne([], 't-1');
        $this->venteAncienne([], 't-2');

        $this->artisan('tagtoa:orders:backfill');

        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));
        $this->assertSame(1, Order::count(), 'Chaque commerce ne voit que son passé.');
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->venteAncienne();

        $this->artisan('tagtoa:orders:backfill', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_a_sale_recorded_after_the_deploy_is_left_alone(): void
    {
        // Les ventes récentes se sont inscrites toutes seules : la reprise ne
        // doit ni les dupliquer ni les réécrire.
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));
        $coca = app(\Modules\Tagtoa\App\Services\Pos\PosCatalog::class)
            ->save($this->caisse(), ['name' => 'Coca', 'price' => 75, 'is_active' => true]);
        app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse(), ['items' => [['ref' => 'pos:'.$coca->id, 'qty' => 1]]]);

        $avant = Order::withoutGlobalScopes()->firstOrFail()->only(['total', 'placed_at']);

        $this->artisan('tagtoa:orders:backfill');

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertEquals($avant['total'], Order::withoutGlobalScopes()->firstOrFail()->total);
    }
}
