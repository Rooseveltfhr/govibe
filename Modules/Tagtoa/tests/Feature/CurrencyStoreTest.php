<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEVISES (CUR-3) — produits de la boutique en ligne en USD / devise personnalisée
|--------------------------------------------------------------------------
| Même modèle que le Menu (CUR-1) : le produit porte sa devise, le marchand saisit ses taux, le
| total de la commande est dans la devise de la boutique, calculé côté serveur au taux ACTIF, la
| ligne fige prix d'origine, devise et taux. Sans taux actif : jamais vendu au prix brut.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use Modules\Tagtoa\App\Models\Store\OrderItem;
use Modules\Tagtoa\App\Models\Store\Product;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Services\Store\StoreOrderService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class CurrencyStoreTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenant = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
        Tenant::flush();
    }

    private function boutique(string $tenant = 't-1'): Store
    {
        return Store::allTenants()->create([
            'tenant_id' => $tenant, 'name' => 'Boutik', 'alias' => 'boutik-'.$tenant,
            'currency' => 'HTG', 'is_published' => true,
        ]);
    }

    private function produit(Store $s, array $attrs = []): Product
    {
        return $s->products()->create(array_merge(['name' => 'Radio', 'price' => 20, 'currency' => 'USD', 'is_available' => true], $attrs));
    }

    private function taux(string $code = 'USD', float $rate = 132, string $tenant = 't-1'): void
    {
        app(CurrencyRateService::class)->save($tenant, 'HTG', ['code' => $code, 'rate' => $rate, 'confirm_jump' => true]);
    }

    private function commander(Store $s, array $items)
    {
        return app(StoreOrderService::class)->placeOrder($s, ['items' => $items, 'customer_name' => 'Jean', 'customer_phone' => '34567890']);
    }

    /* ---------- commande ---------- */

    public function test_an_order_converts_a_foreign_product_server_side_and_freezes_the_rate(): void
    {
        $s = $this->boutique();
        $this->taux('USD', 132);
        $radio = $this->produit($s);                              // 20 $
        $riz = $this->produit($s, ['name' => 'Riz', 'price' => 250, 'currency' => null]);

        $o = $this->commander($s, [['id' => $radio->id, 'qty' => 2], ['id' => $riz->id, 'qty' => 1]]);

        // 20 $ × 132 = 2640 G ; × 2 = 5280 ; + 250 = 5530.
        $this->assertSame('HTG', $o->currency);
        $this->assertSame('5530.00', $o->total);
        $l = OrderItem::where('product_id', $radio->id)->sole();
        $this->assertSame('2640.00', $l->price);
        $this->assertSame('USD', $l->currency_original);
        $this->assertSame('20.00', $l->unit_price_original);
        $this->assertSame('132.000000', $l->rate_used);
        $r = OrderItem::where('product_id', $riz->id)->sole();
        $this->assertNull($r->currency_original, 'Produit en devise de la boutique : comme avant.');

        $this->taux('USD', 140);                                   // le taux change ensuite
        $this->assertSame('5530.00', $o->fresh()->total);
        $this->assertSame('2640.00', $l->fresh()->price);
    }

    public function test_a_custom_currency_converts_too(): void
    {
        $s = $this->boutique();
        $this->taux('$HT', 5);
        $p = $this->produit($s, ['price' => 100, 'currency' => '$HT']);

        $this->assertSame('500.00', $this->commander($s, [['id' => $p->id, 'qty' => 1]])->total);
    }

    public function test_without_an_active_rate_the_product_is_never_sold_at_its_raw_price(): void
    {
        $s = $this->boutique();
        $this->taux('USD', 132);
        $p = $this->produit($s);
        app(CurrencyRateService::class)->toggle(\Modules\Tagtoa\App\Models\Currency\CurrencyRate::allTenants()->sole());

        $this->expectException(OrderRuleException::class);
        $this->expectExceptionMessage('currency_rate_missing');
        $this->commander($s, [['id' => $p->id, 'qty' => 1]]);
    }

    public function test_another_business_rate_is_never_used(): void
    {
        $s = $this->boutique('t-1');
        $this->taux('USD', 132, 't-2');
        $p = $this->produit($s);

        $this->expectExceptionMessage('currency_rate_missing');
        $this->commander($s, [['id' => $p->id, 'qty' => 1]]);
    }

    public function test_the_public_endpoint_explains_a_missing_rate(): void
    {
        $s = $this->boutique();
        $p = $this->produit($s);

        $this->postJson(route('tagtoa.store.order', $s->alias), [
            'items' => [['id' => $p->id, 'qty' => 1]], 'customer_name' => 'Jean', 'customer_phone' => '34567890',
        ])->assertStatus(422)->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Le prix d\'un produit n\'est plus disponible dans la devise de la boutique. Rechargez la page.');
    }

    public function test_a_missing_rate_on_one_product_does_not_block_an_order_for_other_products(): void
    {
        $s = $this->boutique();
        $this->produit($s);                                        // USD sans taux
        $riz = $this->produit($s, ['name' => 'Riz', 'price' => 250, 'currency' => null]);

        $this->assertSame('250.00', $this->commander($s, [['id' => $riz->id, 'qty' => 1]])->total);
    }

    public function test_items_without_currency_keep_their_exact_price(): void
    {
        $s = $this->boutique();
        $p = $this->produit($s, ['price' => 12.40, 'currency' => null]);

        $this->assertSame('124.00', $this->commander($s, [['id' => $p->id, 'qty' => 10]])->total, 'Aucun arrondi sur un produit sans devise.');
    }

    /* ---------- page publique ---------- */

    public function test_the_public_page_shows_the_original_price_and_carts_in_the_store_currency(): void
    {
        $s = $this->boutique();
        $this->taux('USD', 132);
        $p = $this->produit($s, ['price' => 5]);

        $html = $this->get(route('tagtoa.store.show', $s->alias))->assertOk()->getContent();
        $this->assertStringContainsString('$5.00', $html);
        $this->assertStringContainsString('≈ 660.00 HTG', $html);
        $this->assertStringContainsString('data-id="'.$p->id.'" data-name="Radio" data-price="660"', $html);
    }

    public function test_without_a_rate_the_public_page_shows_the_product_as_unavailable(): void
    {
        $s = $this->boutique();
        $p = $this->produit($s);

        $html = $this->get(route('tagtoa.store.show', $s->alias))->assertOk()->getContent();
        $this->assertStringContainsString('Indisponible', $html);
        $this->assertStringNotContainsString('data-id="'.$p->id.'"', $html);
    }

    /* ---------- tableau de bord marchand ---------- */

    public function test_the_merchant_sets_a_product_currency_only_from_the_allowed_choices(): void
    {
        $this->patron();
        $s = $this->boutique();
        $this->taux('USD', 132);

        $enregistrer = fn (string $cur) => $this->put(route('tagtoa.store.dashboard.update', $s->id), [
            'name' => 'Boutik', 'currency' => 'HTG',
            'products' => [['name' => 'Radio', 'price' => 20, 'currency' => $cur]],
        ]);

        $enregistrer('usd')->assertSessionHasNoErrors();
        $this->assertSame('USD', Product::sole()->currency);

        $enregistrer('HTG')->assertSessionHasNoErrors();
        $this->assertNull(Product::sole()->currency, 'Devise de la boutique = NULL (comme l\'existant).');

        $enregistrer('EUR')->assertSessionHasErrors('products');
        $this->assertNull(Product::sole()->currency, 'Rien d\'écrit.');
    }

    public function test_a_rate_of_another_business_cannot_be_used_on_my_product(): void
    {
        $this->taux('USD', 132, 't-2');
        $this->patron('t-1');
        $s = $this->boutique('t-1');

        $this->put(route('tagtoa.store.dashboard.update', $s->id), [
            'name' => 'Boutik', 'currency' => 'HTG', 'products' => [['name' => 'Radio', 'price' => 20, 'currency' => 'USD']],
        ])->assertSessionHasErrors('products');
        $this->assertSame(0, Product::count());
    }

    public function test_saving_without_the_currency_field_keeps_the_product_currency(): void
    {
        $this->patron();
        $s = $this->boutique();
        $this->taux();
        $p = $this->produit($s);

        $this->put(route('tagtoa.store.dashboard.update', $s->id), [
            'name' => 'Boutik', 'currency' => 'HTG', 'products' => [['id' => $p->id, 'name' => 'Radio', 'price' => 25]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('USD', $p->fresh()->currency);
    }

    public function test_the_edit_form_offers_the_store_currency_and_active_rates_only(): void
    {
        $this->patron();
        $s = $this->boutique();
        $this->taux('USD', 132);
        $this->taux('EUR', 150);
        app(CurrencyRateService::class)->toggle(\Modules\Tagtoa\App\Models\Currency\CurrencyRate::allTenants()->where('code', 'EUR')->sole());

        $html = $this->get(route('tagtoa.store.dashboard.edit', $s->id))->assertOk()->getContent();
        $this->assertStringContainsString('name="products[IDX][currency]"', $html);
        $this->assertStringContainsString('value="USD"', $html);
        $this->assertStringNotContainsString('<option value="EUR"', $html, 'Taux désactivé : pas proposé.');
    }

    public function test_the_currency_screen_accepts_a_store_currency_as_base(): void
    {
        $this->patron();
        Store::allTenants()->create(['tenant_id' => 't-1', 'name' => 'Boutik EUR', 'alias' => 'b-eur', 'currency' => 'EUR', 'is_published' => true]);

        $this->post(route('tagtoa.currencies.store'), ['code' => 'USD', 'base_currency' => 'EUR', 'rate' => '0.92'])->assertSessionHasNoErrors();
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000188_add_currency_to_store_products.php';
        $mig->up();
        $mig->up();
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_store_products', 'currency'));
        $this->assertFalse(Schema::hasColumn('tagtoa_store_order_items', 'rate_used'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_store_order_items', 'currency_original'));
    }

    /* ---------- revue indépendante CUR-3 ---------- */

    public function test_a_tiny_rate_never_makes_an_item_free(): void
    {
        $s = $this->boutique();
        $this->taux('USD', 0.000001);
        $p = $this->produit($s, ['price' => 1]);

        $this->expectExceptionMessage('currency_rate_missing');
        $this->commander($s, [['id' => $p->id, 'qty' => 1]]);
    }

    public function test_an_amount_beyond_the_columns_is_refused_not_a_500(): void
    {
        $s = $this->boutique();
        $this->taux('USD', 1000000);
        $p = $this->produit($s, ['price' => 20000]);

        $this->postJson(route('tagtoa.store.order', $s->alias), [
            'items' => [['id' => $p->id, 'qty' => 1]], 'customer_name' => 'Jean', 'customer_phone' => '34567890',
        ])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(0, \Modules\Tagtoa\App\Models\Store\Order::count());
    }

    public function test_a_rate_changed_since_the_page_loaded_asks_the_customer_to_reconfirm(): void
    {
        $s = $this->boutique();
        $this->taux('USD', 160);
        $p = $this->produit($s, ['price' => 5]);   // le client a vu 660, le serveur calcule 800

        $this->postJson(route('tagtoa.store.order', $s->alias), [
            'items' => [['id' => $p->id, 'qty' => 1]], 'customer_name' => 'Jean', 'customer_phone' => '34567890',
            'expected_total' => 660,
        ])->assertStatus(422)->assertJsonPath('ok', false)->assertJsonPath('new_total', 800);
        $this->assertSame(0, \Modules\Tagtoa\App\Models\Store\Order::count());

        $this->postJson(route('tagtoa.store.order', $s->alias), [
            'items' => [['id' => $p->id, 'qty' => 1]], 'customer_name' => 'Jean', 'customer_phone' => '34567890',
            'expected_total' => 800,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_a_failed_currency_validation_writes_nothing_not_even_the_store(): void
    {
        $this->patron();
        $s = $this->boutique();
        $this->taux('USD', 132);

        $this->put(route('tagtoa.store.dashboard.update', $s->id), [
            'name' => 'Autre nom', 'currency' => 'USD',
            'products' => [['name' => 'Radio', 'price' => 20, 'currency' => 'EUR']],
        ])->assertSessionHasErrors('products');

        $s->refresh();
        $this->assertSame('HTG', $s->currency);
        $this->assertSame('Boutik', $s->name);
    }

    public function test_an_array_currency_and_absurd_prices_are_validation_errors(): void
    {
        $this->patron();
        $s = $this->boutique();

        $this->put(route('tagtoa.store.dashboard.update', $s->id), [
            'name' => 'Boutik', 'currency' => 'HTG', 'products' => [['name' => 'A', 'price' => 1, 'currency' => ['x']]],
        ])->assertSessionHasErrors('products');
        $this->put(route('tagtoa.store.dashboard.update', $s->id), [
            'name' => 'Boutik', 'currency' => 'HTG', 'products' => [['name' => 'A', 'price' => 1e12]],
        ])->assertSessionHasErrors('products');
        $this->assertSame(0, Product::count());
    }

    public function test_the_store_currency_is_normalised_and_validated(): void
    {
        $this->patron();
        $s = $this->boutique();

        $this->put(route('tagtoa.store.dashboard.update', $s->id), ['name' => 'Boutik', 'currency' => ' usd '])
            ->assertSessionHasNoErrors();
        $this->assertSame('USD', $s->fresh()->currency);

        $this->put(route('tagtoa.store.dashboard.update', $s->id), ['name' => 'Boutik', 'currency' => 'x y!'])
            ->assertSessionHasErrors('currency');
    }
}
