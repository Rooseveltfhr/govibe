<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Exceptions\InsufficientStockException;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — un article à stock suivi ne devait jamais passer sous zéro,
| et pourtant rien ne l'empêchait : deux caisses vendant le dernier article
| en même temps, ou un code scanné deux fois de suite, écrivaient un stock
| négatif en silence. StockLedger::apply() est le SEUL chemin qui touche la
| colonne stock (verrou de ligne compris) : c'est là, et nulle part
| ailleurs, que la garde doit vivre pour protéger POS, MENU et la boutique
| à la fois.
|--------------------------------------------------------------------------
*/
class PosOversellGuardTest extends TestCase
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

    private function article(Terminal $caisse, float $stock): Product
    {
        return app(PosCatalog::class)->save($caisse, [
            'name' => 'Prestige', 'price' => 150, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    /* ------------------------------------------------------------------
       StockLedger::apply() — la garde elle-même.
       ------------------------------------------------------------------ */

    public function test_a_removal_that_would_go_below_zero_is_refused(): void
    {
        $this->patron();
        $article = $this->article($this->caisse(), 3);

        $this->expectException(InsufficientStockException::class);

        app(StockLedger::class)->remove($article, 5, MovementType::SALE);
    }

    public function test_a_removal_that_lands_exactly_on_zero_is_allowed(): void
    {
        // La frontière : vendre EXACTEMENT ce qu'il reste n'est pas une
        // survente. Refuser ce cas gênerait un commerce qui écoule son stock
        // jusqu'au dernier article, ce qui est un usage tout à fait normal.
        $this->patron();
        $article = $this->article($this->caisse(), 3);

        $mouvement = app(StockLedger::class)->remove($article, 3, MovementType::SALE);

        $this->assertSame(0.0, $mouvement->stock_after);
        $this->assertSame(0.0, (float) $article->fresh()->stock);
    }

    public function test_an_untracked_article_has_no_ceiling(): void
    {
        // Stock = null : « non suivi », donc jamais de rupture à signaler.
        $this->patron();
        $article = app(PosCatalog::class)->save($this->caisse(), [
            'name' => 'Service', 'price' => 500, 'stock' => null, 'is_active' => true,
        ]);

        $mouvement = app(StockLedger::class)->remove($article, 999, MovementType::SALE);

        $this->assertNull($mouvement);
        $this->assertNull($article->fresh()->stock);
    }

    /* ------------------------------------------------------------------
       À l'encaissement (POS) — le caissier voit un message, pas un plantage.
       ------------------------------------------------------------------ */

    public function test_selling_more_than_the_stock_is_refused_with_a_clear_message(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = $this->article($caisse, 2);

        $reponse = $this->postJson(route('tagtoa.pos.sale', $caisse->id), [
            'items' => [['product_id' => $article->id, 'qty' => 5, 'name' => 'Prestige', 'price' => 150]],
        ]);

        $reponse->assertStatus(409)->assertJson(['ok' => false]);
        $this->assertStringContainsString('Prestige', $reponse->json('error'));

        // Rien n'a bougé : ni le stock, ni une vente fantôme en base.
        $this->assertSame(2.0, (float) $article->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_a_sale_within_stock_still_succeeds(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = $this->article($caisse, 10);

        $this->postJson(route('tagtoa.pos.sale', $caisse->id), [
            'items' => [['product_id' => $article->id, 'qty' => 4, 'name' => 'Prestige', 'price' => 150]],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(6.0, (float) $article->fresh()->stock);
        $this->assertSame(1, Sale::count());
    }

    /* ------------------------------------------------------------------
       Rejeu hors ligne (sync) — une ligne en échec ne bloque pas les autres.
       ------------------------------------------------------------------ */

    public function test_an_offline_sale_that_oversells_is_reported_without_blocking_the_rest(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = $this->article($caisse, 1);

        $reponse = $this->postJson(route('tagtoa.pos.sync', $caisse->id), [
            'sales' => [
                ['client_uuid' => 'a', 'items' => [['product_id' => $article->id, 'qty' => 1, 'name' => 'Prestige', 'price' => 150]]],
                // Rejouée après coup : il ne reste plus rien.
                ['client_uuid' => 'b', 'items' => [['product_id' => $article->id, 'qty' => 1, 'name' => 'Prestige', 'price' => 150]]],
            ],
        ]);

        $resultats = $reponse->json('results');
        $this->assertTrue($resultats[0]['ok']);
        $this->assertFalse($resultats[1]['ok']);
        $this->assertStringContainsString('Prestige', $resultats[1]['error']);
        $this->assertSame(0.0, (float) $article->fresh()->stock);
    }
}
