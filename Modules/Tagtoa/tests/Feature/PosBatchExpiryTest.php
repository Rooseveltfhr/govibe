<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\ProductBatch;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Inventory\BatchService;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — une pharmacie reçoit le même médicament plusieurs fois, avec
| une péremption différente à chaque fois. `purchased_at` (une seule date,
| globale à l'article) ne permettait pas de les distinguer, ni d'alerter
| sur ce qui périme bientôt.
|--------------------------------------------------------------------------
*/
class PosBatchExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    public function test_receiving_a_batch_raises_the_products_stock(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Amoxicilline', 'price' => 200, 'stock' => 10, 'is_active' => true]);

        app(BatchService::class)->receive($article, 50, ['expires_at' => now()->addMonths(6)->toDateString()]);

        $this->assertSame(60.0, (float) $article->fresh()->stock);
        $this->assertSame(1, ProductBatch::count());
    }

    public function test_two_batches_of_the_same_product_keep_different_expiry_dates(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Amoxicilline', 'price' => 200, 'stock' => 0, 'is_active' => true]);

        app(BatchService::class)->receive($article, 200, ['expires_at' => now()->addMonths(1)->toDateString()]);
        app(BatchService::class)->receive($article, 100, ['expires_at' => now()->addMonths(6)->toDateString()]);

        $this->assertSame(2, ProductBatch::where('product_id', $article->id)->count());
        $this->assertSame(300.0, (float) $article->fresh()->stock);
    }

    public function test_the_expiring_soon_list_only_shows_batches_within_the_window(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Sirop', 'price' => 300, 'stock' => 0, 'is_active' => true]);

        $bientotDate = now()->addDays(5)->toDateString();
        app(BatchService::class)->receive($article, 10, ['expires_at' => $bientotDate]);
        app(BatchService::class)->receive($article, 10, ['expires_at' => now()->addDays(90)->toDateString()]);

        $bientot = app(BatchService::class)->expiringWithin(30);

        $this->assertCount(1, $bientot);
        $this->assertSame($bientotDate, $bientot->first()->expires_at->toDateString());
    }

    public function test_batches_never_leak_between_shops(): void
    {
        $this->patron('t-1');
        $mine = app(PosCatalog::class)->save($this->caisse('t-1'), ['name' => 'Sirop', 'price' => 300, 'is_active' => true]);
        app(BatchService::class)->receive($mine, 10, ['expires_at' => now()->addDays(5)->toDateString()]);

        $this->patron('t-2');
        $theirs = app(PosCatalog::class)->save($this->caisse('t-2'), ['name' => 'Sirop', 'price' => 300, 'is_active' => true]);
        app(BatchService::class)->receive($theirs, 10, ['expires_at' => now()->addDays(5)->toDateString()]);

        $this->assertCount(1, app(BatchService::class)->expiringWithin(30));
    }

    public function test_the_lots_screen_lets_the_owner_receive_a_batch(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Amoxicilline', 'price' => 200, 'stock' => 0, 'is_active' => true]);

        $this->post(route('tagtoa.pos.lots.store'), [
            'product_id' => $article->id, 'quantity' => 50,
            'expires_at' => now()->addMonths(3)->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(50.0, (float) $article->fresh()->stock);
    }

    public function test_a_cashier_cannot_receive_a_batch(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Amoxicilline', 'price' => 200, 'stock' => 0, 'is_active' => true]);
        app(StaffService::class)->save($caisse->tenant_id, [
            'name' => 'Employé', 'pin' => '1234', 'role' => StaffAccess::ROLE_CASHIER, 'terminal_id' => $caisse->id,
        ]);
        $this->post(route('tagtoa.pos.staff.login', $caisse->id), ['pin' => '1234']);

        $this->post(route('tagtoa.pos.lots.store'), [
            'product_id' => $article->id, 'quantity' => 50,
        ])->assertForbidden();

        $this->assertSame(0.0, (float) $article->fresh()->stock);
    }

    public function test_a_foreign_product_id_is_refused(): void
    {
        $this->patron('t-1');
        $this->caisse('t-1');
        $foreign = app(PosCatalog::class)->save($this->caisse('t-2'), ['name' => 'Leur article', 'price' => 100, 'is_active' => true]);

        $this->patron('t-1');
        $this->post(route('tagtoa.pos.lots.store'), [
            'product_id' => $foreign->id, 'quantity' => 10,
        ])->assertNotFound();

        $this->assertSame(0, ProductBatch::count());
    }
}
