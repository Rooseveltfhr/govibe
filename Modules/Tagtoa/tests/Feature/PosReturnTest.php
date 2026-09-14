<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleItem;
use Modules\Tagtoa\App\Models\Pos\SaleReturn;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Inventory\StockMovement;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\ReturnService;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

/**
 * LES RETOURS — la seule action du back-office qui fasse SORTIR de l'argent.
 *
 * Quatre règles gouvernent ce fichier, et chacune a son test :
 *   1. le montant ne vient jamais du navigateur ;
 *   2. on ne rend jamais plus qu'on n'a vendu, retours passés compris ;
 *   3. un double envoi ne rembourse qu'une fois ;
 *   4. la vente d'origine ne bouge pas.
 */
class PosReturnTest extends TestCase
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

    private function article(array $attrs = []): Product
    {
        return app(PosCatalog::class)->save($this->caisse(), array_merge([
            'name' => 'Prestige', 'price' => 150, 'cost_price' => 100,
            'stock' => 20, 'is_active' => true,
        ], $attrs));
    }

    /** Une vente de 2 Prestige à 150, taxe 10 % incluse dans la ligne. */
    private function vente(float $qty = 2, float $prix = 150, float $taxe = 30): Sale
    {
        $p = $this->article();

        $sale = Sale::create([
            'terminal_id' => $this->caisse()->id,
            'reference'   => 'V-'.random_int(1000, 9999),
            'subtotal'    => $prix * $qty, 'discount' => 0,
            'tax_base'    => $prix * $qty, 'tax_total' => $taxe,
            'total'       => $prix * $qty + $taxe,
            'currency'    => 'HTG',
            'payments'    => [['method' => 'cash', 'amount' => $prix * $qty + $taxe]],
            'status'      => 1,
            'sold_at'     => now(),
        ]);

        SaleItem::create([
            'sale_id'    => $sale->id, 'product_id' => $p->id, 'source' => 'pos',
            'name'       => $p->name, 'price' => $prix, 'cost_price' => 100,
            'qty'        => $qty, 'line_total' => $prix * $qty,
            'tax_rate'   => 10, 'tax_amount' => $taxe,
        ]);

        return $sale->load('items');
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    /* ------------------------------------------------------------------
       1. Le montant ne vient jamais du navigateur.
       ------------------------------------------------------------------ */

    public function test_the_refund_is_computed_from_the_frozen_sale_price(): void
    {
        $this->patron();
        $sale = $this->vente(qty: 2, prix: 150, taxe: 30);
        $ligne = $sale->items->first();

        // Le prix du CATALOGUE change après la vente. Le remboursement doit
        // suivre le ticket, pas le catalogue d'aujourd'hui.
        Product::whereKey($ligne->product_id)->update(['price' => 999]);

        $r = $this->service()->record($sale, [$ligne->id => 1], ['tenant_id' => 't-1']);

        $this->assertSame(ReturnService::OK, $r['result']);
        $this->assertEquals(150.0, (float) $r['return']->subtotal);
    }

    public function test_the_tax_is_returned_in_proportion(): void
    {
        // Rendre la moitié d'une ligne rend la moitié de sa taxe. Rendre la
        // taxe entière ferait sortir de la caisse une taxe jamais collectée.
        $this->patron();
        $sale = $this->vente(qty: 2, prix: 150, taxe: 30);
        $ligne = $sale->items->first();

        $r = $this->service()->record($sale, [$ligne->id => 1], ['tenant_id' => 't-1']);

        $this->assertEquals(15.0, (float) $r['return']->tax_total);
        $this->assertEquals(165.0, (float) $r['return']->total);
    }

    /* ------------------------------------------------------------------
       2. On ne rend jamais plus qu'on n'a vendu.
       ------------------------------------------------------------------ */

    public function test_returning_more_than_sold_is_refused(): void
    {
        $this->patron();
        $sale = $this->vente(qty: 2);

        $r = $this->service()->record($sale, [$sale->items->first()->id => 3], ['tenant_id' => 't-1']);

        $this->assertSame(ReturnService::TROP, $r['result']);
        $this->assertSame(0, SaleReturn::count());
    }

    public function test_the_cap_counts_every_earlier_return(): void
    {
        // LE test de la règle. Trois retours d'une unité sur un article vendu
        // deux fois : chacun est valable pris isolément, et sans cumul les
        // trois passeraient — le commerce rembourserait un article qu'il n'a
        // jamais vendu.
        $this->patron();
        $sale = $this->vente(qty: 2);
        $ligne = $sale->items->first();

        foreach ([1, 1] as $i => $q) {
            $r = $this->service()->record($sale->fresh('items'), [$ligne->id => $q],
                ['tenant_id' => 't-1', 'idempotency_key' => 'k'.$i]);
            $this->assertSame(ReturnService::OK, $r['result']);
        }

        $troisieme = $this->service()->record($sale->fresh('items'), [$ligne->id => 1],
            ['tenant_id' => 't-1', 'idempotency_key' => 'k3']);

        $this->assertSame(ReturnService::TROP, $troisieme['result']);
        $this->assertSame(2, SaleReturn::count());
    }

    public function test_a_line_from_another_sale_is_refused(): void
    {
        // Un identifiant de ligne vient du navigateur : sans contrôle, on
        // rembourserait sur une vente qu'on n'a pas ouverte.
        $this->patron();
        $a = $this->vente();
        $b = $this->vente();

        $r = $this->service()->record($a, [$b->items->first()->id => 1], ['tenant_id' => 't-1']);

        $this->assertSame(ReturnService::INTROUVABLE, $r['result']);
        $this->assertSame(0, SaleReturn::count());
    }

    public function test_nothing_to_return_is_said_plainly(): void
    {
        $this->patron();
        $sale = $this->vente();

        $r = $this->service()->record($sale, [$sale->items->first()->id => 0], ['tenant_id' => 't-1']);

        $this->assertSame(ReturnService::RIEN, $r['result']);
    }

    /* ------------------------------------------------------------------
       3. Un double envoi ne rembourse qu'une fois.
       ------------------------------------------------------------------ */

    public function test_the_same_key_refunds_only_once(): void
    {
        // Un téléphone lent, un doigt qui insiste, une connexion qui repart.
        // Sans clé, la caisse paie deux fois.
        $this->patron();
        $sale = $this->vente(qty: 2);
        $ligne = $sale->items->first();

        $un   = $this->service()->record($sale, [$ligne->id => 1], ['tenant_id' => 't-1', 'idempotency_key' => 'abc']);
        $deux = $this->service()->record($sale->fresh('items'), [$ligne->id => 1], ['tenant_id' => 't-1', 'idempotency_key' => 'abc']);

        $this->assertSame(ReturnService::OK, $un['result']);
        $this->assertSame(ReturnService::DEJA_FAIT, $deux['result']);
        $this->assertSame($un['return']->id, $deux['return']->id);
        $this->assertSame(1, SaleReturn::count());
    }

    public function test_a_replay_returns_the_existing_document_not_an_error(): void
    {
        // Répondre « erreur » ferait recommencer le caissier — et c'est ainsi
        // qu'on rembourse deux fois.
        $this->patron();
        $sale = $this->vente();
        $ligne = $sale->items->first();

        $this->service()->record($sale, [$ligne->id => 1], ['tenant_id' => 't-1', 'idempotency_key' => 'zz']);
        $rejeu = $this->service()->record($sale->fresh('items'), [$ligne->id => 1], ['tenant_id' => 't-1', 'idempotency_key' => 'zz']);

        $this->assertNotNull($rejeu['return']);
        $this->assertNotNull($rejeu['return']->reference);
    }

    /* ------------------------------------------------------------------
       4. La vente ne bouge pas.
       ------------------------------------------------------------------ */

    public function test_the_original_sale_is_never_rewritten(): void
    {
        // Réécrire la vente changerait le rapport Z d'une journée close et
        // rendrait faux le ticket déjà remis au client. Dans un commerce où le
        // patron n'est pas derrière la caisse, c'est aussi la porte ouverte au
        // vol : on encaisse, on efface la ligne, on garde l'argent.
        $this->patron();
        $sale = $this->vente(qty: 2, prix: 150, taxe: 30);
        $avant = [$sale->total, $sale->subtotal, $sale->tax_total, $sale->items->first()->qty];

        $this->service()->record($sale, [$sale->items->first()->id => 1], ['tenant_id' => 't-1']);

        $sale->refresh()->load('items');
        $this->assertEquals($avant[0], $sale->total);
        $this->assertEquals($avant[1], $sale->subtotal);
        $this->assertEquals($avant[2], $sale->tax_total);
        $this->assertEquals($avant[3], $sale->items->first()->qty);
    }

    /* ------------------------------------------------------------------
       Le stock.
       ------------------------------------------------------------------ */

    public function test_the_goods_go_back_on_the_shelf_through_the_ledger(): void
    {
        // Jamais d'écriture directe sur la colonne de stock : le mouvement doit
        // se raconter, sinon le commerce ne peut plus expliquer un écart.
        $this->patron();
        $sale = $this->vente(qty: 2);
        $ligne = $sale->items->first();
        $stockAvant = (float) Product::find($ligne->product_id)->stock;

        $this->service()->record($sale, [$ligne->id => 2], ['tenant_id' => 't-1']);

        $this->assertSame($stockAvant + 2, (float) Product::find($ligne->product_id)->stock);

        $mvt = StockMovement::where('type', MovementType::RETURN_IN)->latest('id')->first();
        $this->assertNotNull($mvt, 'Le retour doit laisser une ligne au journal de stock.');
        $this->assertSame(2.0, (float) $mvt->delta);
        $this->assertSame('pos_return', $mvt->origin_type);
    }

    public function test_a_defective_item_does_not_go_back_on_sale(): void
    {
        // Le remettre au stock ferait croire au marchand qu'il possède une
        // marchandise qu'il va jeter — et il ne recommanderait pas à temps.
        $this->patron();
        $sale = $this->vente(qty: 2);
        $ligne = $sale->items->first();
        $avant = (float) Product::find($ligne->product_id)->stock;

        $r = $this->service()->record($sale, [$ligne->id => 1],
            ['tenant_id' => 't-1', 'kind' => 'defective']);

        $this->assertSame(ReturnService::OK, $r['result']);
        $this->assertSame($avant, (float) Product::find($ligne->product_id)->stock);
        // L'argent sort quand même : le client est remboursé.
        $this->assertGreaterThan(0, (float) $r['return']->total);
    }

    public function test_the_merchant_can_refuse_to_restock(): void
    {
        $this->patron();
        $sale = $this->vente(qty: 2);
        $ligne = $sale->items->first();
        $avant = (float) Product::find($ligne->product_id)->stock;

        $this->service()->record($sale, [$ligne->id => 1],
            ['tenant_id' => 't-1', 'restock' => false]);

        $this->assertSame($avant, (float) Product::find($ligne->product_id)->stock);
    }

    public function test_a_deleted_article_does_not_break_the_refund(): void
    {
        // Le client revient avec un article retiré du catalogue entre-temps.
        // On ne peut plus le remettre en stock — mais l'argent doit sortir.
        $this->patron();
        $sale = $this->vente();
        $ligne = $sale->items->first();
        Product::whereKey($ligne->product_id)->delete();

        $r = $this->service()->record($sale, [$ligne->id => 1], ['tenant_id' => 't-1']);

        $this->assertSame(ReturnService::OK, $r['result']);
        $this->assertGreaterThan(0, (float) $r['return']->total);
    }

    /* ------------------------------------------------------------------
       La colonne vertébrale suit.
       ------------------------------------------------------------------ */

    public function test_a_full_refund_marks_the_order_refunded(): void
    {
        // Sans cela, l'écran Commandes continuerait d'afficher « payée » une
        // vente dont l'argent est ressorti, et le chiffre d'affaires du jour
        // compterait une recette que le commerce n'a plus.
        $this->patron();
        $sale = $this->vente(qty: 1, prix: 150, taxe: 0);

        $order = Order::create([
            'tenant_id' => 't-1', 'channel' => 'pos', 'source_type' => 'pos_sale',
            'source_id' => $sale->id, 'reference' => $sale->reference,
            'subtotal' => 150, 'discount' => 0, 'tax_base' => 150, 'tax_total' => 0,
            'total' => 150, 'currency' => 'HTG',
            'status' => OrderStatus::COMPLETED, 'payment_status' => OrderStatus::PAID,
            'placed_at' => now(),
        ]);

        $this->service()->record($sale, [$sale->items->first()->id => 1], ['tenant_id' => 't-1']);

        $order->refresh();
        $this->assertSame(OrderStatus::REFUND, $order->payment_status);
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
    }

    public function test_a_partial_refund_is_not_a_full_one(): void
    {
        $this->patron();
        $sale = $this->vente(qty: 2, prix: 150, taxe: 0);

        $order = Order::create([
            'tenant_id' => 't-1', 'channel' => 'pos', 'source_type' => 'pos_sale',
            'source_id' => $sale->id, 'reference' => $sale->reference,
            'subtotal' => 300, 'discount' => 0, 'tax_base' => 300, 'tax_total' => 0,
            'total' => 300, 'currency' => 'HTG',
            'status' => OrderStatus::COMPLETED, 'payment_status' => OrderStatus::PAID,
            'placed_at' => now(),
        ]);

        $this->service()->record($sale, [$sale->items->first()->id => 1], ['tenant_id' => 't-1']);

        $this->assertSame(OrderStatus::PARTIAL, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::COMPLETED, $order->status, 'Une commande à moitié rendue reste servie.');
    }

    /* ------------------------------------------------------------------
       Les écrans et le cloisonnement.
       ------------------------------------------------------------------ */

    public function test_the_screens_open(): void
    {
        $this->patron();
        $sale = $this->vente();

        $this->get(route('tagtoa.pos.returns'))->assertOk();
        $this->get(route('tagtoa.pos.returns.create', $sale->id))->assertOk()->assertSee('Prestige');
    }

    public function test_a_sale_of_another_business_cannot_be_refunded(): void
    {
        // Sans cloisonnement, on rembourserait sur la vente du voisin — avec
        // NOTRE argent.
        $this->patron('t-2');
        $autre = $this->vente(); // créée sous la caisse de t-1 par le helper

        $this->patron('t-9');
        $this->get(route('tagtoa.pos.returns.create', $autre->id))->assertNotFound();

        $this->post(route('tagtoa.pos.returns.store', $autre->id), [
            'qty' => [$autre->items->first()->id => 1], 'idempotency_key' => 'x',
        ])->assertNotFound();

        $this->assertSame(0, SaleReturn::withoutGlobalScopes()->count());
    }

    public function test_the_form_records_a_return_end_to_end(): void
    {
        $this->patron();
        $sale = $this->vente(qty: 2, prix: 150, taxe: 30);

        $this->post(route('tagtoa.pos.returns.store', $sale->id), [
            'qty'             => [$sale->items->first()->id => 1],
            'kind'            => 'customer',
            'reason'          => 'Trop chaud',
            'restock'         => 1,
            'idempotency_key' => 'form-1',
        ])->assertRedirect(route('tagtoa.pos.returns'))->assertSessionHas('success');

        $r = SaleReturn::firstOrFail();
        $this->assertEquals(165.0, (float) $r->total);
        $this->assertSame('Trop chaud', $r->reason);
    }

    public function test_the_form_never_accepts_an_amount(): void
    {
        // Le formulaire dit QUELLES lignes et COMBIEN d'unités — jamais combien
        // d'argent. Un champ de montant serait un chèque en blanc.
        $vue = (string) file_get_contents(__DIR__.'/../../resources/views/pos/return-form.blade.php');

        $this->assertStringNotContainsString('name="total"', $vue);
        $this->assertStringNotContainsString('name="amount"', $vue);
        $this->assertStringNotContainsString('name="subtotal"', $vue);
    }
}
