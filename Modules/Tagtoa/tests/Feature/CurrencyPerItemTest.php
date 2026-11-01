<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEVISES (CUR-1) — devise par article + taux de change du marchand
|--------------------------------------------------------------------------
| Un article peut être en USD ou en devise personnalisée ($HT). Le total d'une commande est
| TOUJOURS dans la devise du menu, calculé côté serveur avec le taux en vigueur, et chaque
| ligne fige le prix d'origine et le taux. Sans taux actif : jamais vendu au prix brut.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use Modules\Tagtoa\App\Models\Currency\CurrencyRate;
use Modules\Tagtoa\App\Models\Currency\CurrencyRateHistory;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\ItemOption;
use Modules\Tagtoa\App\Models\Menu\ItemOptionChoice;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Services\Currency\RateRuleException;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pos\MenuProductSync;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class CurrencyPerItemTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::allTenants()->create([
            'tenant_id' => $tenantId, 'name' => 'Lakay Grill', 'alias' => 'lakay-'.uniqid(),
            'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true, 'show_prices' => true,
        ]);
    }

    private function article(Menu $menu, array $attrs = []): Item
    {
        $cat = Category::firstOrCreate(['menu_id' => $menu->id, 'name' => 'Plats'], ['is_active' => true]);

        return Item::create(array_merge(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Steak', 'price' => 5, 'is_available' => true], $attrs));
    }

    private function taux(string $code = 'USD', float $rate = 132, string $tenant = 't-1', string $base = 'HTG'): CurrencyRate
    {
        return app(CurrencyRateService::class)->save($tenant, $base, ['code' => $code, 'rate' => $rate, 'confirm_jump' => true]);
    }

    private function commander(Menu $menu, array $items)
    {
        return app(MenuOrderService::class)->placeOrder($menu, ['items' => $items, 'order_type' => 'dine_in']);
    }

    /* ---------- taux ---------- */

    public function test_the_merchant_sets_rates_and_every_change_is_journaled(): void
    {
        $this->patron();
        $this->menu();

        $this->post(route('tagtoa.currencies.store'), ['code' => 'usd', 'base_currency' => 'HTG', 'rate' => '132', 'symbol' => '$'])
            ->assertSessionHasNoErrors();
        $r = CurrencyRate::sole();
        $this->assertSame('USD', $r->code, 'Code mis en majuscules.');
        $this->assertSame('t-1', $r->tenant_id);
        $this->assertSame('132.000000', $r->rate);

        $this->post(route('tagtoa.currencies.store'), ['code' => 'USD', 'base_currency' => 'HTG', 'rate' => '135'])->assertSessionHasNoErrors();
        $this->assertSame('135.000000', $r->fresh()->rate);
        $this->assertSame(1, CurrencyRate::count(), 'Mise à jour, pas de doublon.');

        $h = CurrencyRateHistory::orderBy('id')->get();
        $this->assertSame(['created', 'rate'], $h->pluck('event')->all());
        $this->assertSame('132.000000', $h[1]->old_rate);
        $this->assertSame('135.000000', $h[1]->new_rate);

        $this->get(route('tagtoa.currencies.index'))->assertOk()->assertSee('1 USD = 135 HTG');
    }

    public function test_a_jump_of_more_than_half_needs_an_explicit_confirmation(): void
    {
        $this->patron();
        $this->menu();
        $this->taux('USD', 132);

        $this->post(route('tagtoa.currencies.store'), ['code' => 'USD', 'base_currency' => 'HTG', 'rate' => '1320'])
            ->assertSessionHasErrors('rate')->assertSessionHas('needs_confirm', true);
        $this->assertSame('132.000000', CurrencyRate::sole()->rate, 'Faute de frappe probable : rien n\'est changé.');

        $this->post(route('tagtoa.currencies.store'), ['code' => 'USD', 'base_currency' => 'HTG', 'rate' => '1320', 'confirm_jump' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('1320.000000', CurrencyRate::sole()->rate);
    }

    /** @dataProvider invalidRates */
    public function test_invalid_rates_are_refused(array $input, string $key): void
    {
        $this->patron();
        $this->menu();

        $this->post(route('tagtoa.currencies.store'), array_merge(['code' => 'USD', 'base_currency' => 'HTG', 'rate' => '132'], $input))
            ->assertSessionHasErrors($key);
        $this->assertSame(0, CurrencyRate::count());
    }

    public static function invalidRates(): array
    {
        return [
            'taux nul'          => [['rate' => '0'], 'rate'],
            'taux négatif'      => [['rate' => '-5'], 'rate'],
            'taux énorme'       => [['rate' => '99999999'], 'rate'],
            'code avec balise'  => [['code' => '<b>'], 'rate'],
            'code trop long'    => [['code' => 'ABCDEFG'], 'rate'],
            'vers elle-même'    => [['code' => 'HTG'], 'rate'],
            'base inconnue'     => [['base_currency' => 'XYZ'], 'base_currency'],
        ];
    }

    public function test_custom_currency_like_haitian_dollar_is_accepted(): void
    {
        $r = $this->taux('$HT', 5);
        $this->assertSame('$HT', $r->code);
        $this->assertSame(25.0, CurrencyRateService::convert(5, '$HT', 'HTG', ['$HT' => $r]));
    }

    public function test_rate_history_is_append_only(): void
    {
        $this->taux();
        $h = CurrencyRateHistory::allTenants()->first();

        $this->expectException(\LogicException::class);
        $h->update(['new_rate' => 1]);
    }

    public function test_another_business_never_sees_nor_toggles_my_rates(): void
    {
        $this->patron('t-1');
        $this->menu('t-1');
        $mine = $this->taux('USD', 132, 't-1');

        $this->patron('t-2');
        $this->menu('t-2');
        $this->post(route('tagtoa.currencies.toggle', $mine->id))->assertNotFound();
        $this->get(route('tagtoa.currencies.index'))->assertOk()->assertDontSee('1 USD = 132');
        $this->assertTrue($mine->fresh()->is_active);
    }

    /* ---------- formulaire article ---------- */

    public function test_the_item_form_offers_the_menu_currency_and_active_rates_before_the_price(): void
    {
        $this->patron();
        $menu = $this->menu();
        $this->taux('USD', 132);
        $this->taux('EUR', 150);
        CurrencyRate::where('code', 'EUR')->first()->update(['is_active' => false]);

        $html = $this->get(route('tagtoa.menu.dashboard.items.create', $menu->id))->assertOk()->getContent();
        $this->assertStringContainsString('name="item_currency"', $html);
        $this->assertStringContainsString('value="HTG"', $html);
        $this->assertStringContainsString('value="USD"', $html);
        $this->assertStringNotContainsString('value="EUR"', $html, 'Taux désactivé : pas proposé.');
        $this->assertLessThan(strpos($html, 'name="item_price"'), strpos($html, 'name="item_currency"'), 'La devise vient AVANT le prix.');
    }

    public function test_an_item_is_saved_in_usd_and_back_in_the_menu_currency(): void
    {
        $this->patron();
        $menu = $this->menu();
        $this->taux('USD', 132);

        $post = fn ($cur) => $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats', 'item_name' => 'Steak '.$cur, 'item_price' => 5, 'item_currency' => $cur,
        ]);

        $post('USD')->assertSessionHasNoErrors();
        $this->assertSame('USD', Item::where('name', 'Steak USD')->sole()->currency);

        $post('HTG')->assertSessionHasNoErrors();
        $this->assertNull(Item::where('name', 'Steak HTG')->sole()->currency, 'Devise du menu = NULL (comme l\'existant).');

        $post('EUR')->assertSessionHasErrors('item_currency');
        $this->assertSame(0, Item::where('name', 'Steak EUR')->count());
    }

    public function test_a_rate_of_another_business_cannot_be_used_on_my_item(): void
    {
        $this->taux('USD', 132, 't-2');
        $this->patron('t-1');
        $menu = $this->menu('t-1');

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats', 'item_name' => 'Steak', 'item_price' => 5, 'item_currency' => 'USD',
        ])->assertSessionHasErrors('item_currency');
    }

    public function test_editing_without_the_field_keeps_the_currency(): void
    {
        $this->patron();
        $menu = $this->menu();
        $this->taux();
        $item = $this->article($menu, ['currency' => 'USD']);

        $this->put(route('tagtoa.menu.dashboard.items.update', [$menu->id, $item->id]), [
            'category_id' => $item->category_id, 'item_name' => 'Steak', 'item_price' => 6,
        ])->assertSessionHasNoErrors();
        $this->assertSame('USD', $item->fresh()->currency);
    }

    /* ---------- commande ---------- */

    public function test_an_order_converts_foreign_items_server_side_and_freezes_the_rate(): void
    {
        $menu = $this->menu();
        $this->taux('USD', 132);
        $steak = $this->article($menu, ['price' => 5, 'currency' => 'USD']);
        $taille = ItemOption::create(['item_id' => $steak->id, 'name' => 'Taille', 'required' => false, 'multiple' => false]);
        $double = ItemOptionChoice::create(['option_id' => $taille->id, 'label' => 'Double', 'price_delta' => 2]);
        $riz = $this->article($menu, ['name' => 'Riz', 'price' => 250]);

        $order = $this->commander($menu, [
            ['id' => $steak->id, 'qty' => 2, 'options' => [$double->id], 'price' => 1],
            ['id' => $riz->id, 'qty' => 1],
        ]);

        // (5 + 2) $ × 132 = 924 G ; × 2 = 1848 ; + 250 = 2098.
        $this->assertSame('HTG', $order->currency);
        $this->assertSame('2098.00', $order->subtotal);
        $ligne = $order->items()->where('item_id', $steak->id)->sole();
        $this->assertSame('924.00', $ligne->price);
        $this->assertSame('USD', $ligne->currency_original);
        $this->assertSame('7.00', $ligne->unit_price_original);
        $this->assertSame('132.000000', $ligne->rate_used);

        $ligneRiz = $order->items()->where('item_id', $riz->id)->sole();
        $this->assertNull($ligneRiz->currency_original, 'Article en devise du menu : comme avant.');
        $this->assertNull($ligneRiz->rate_used);

        // Changer le taux ensuite ne touche jamais la commande passée.
        $this->taux('USD', 140);
        $this->assertSame('2098.00', $order->fresh()->subtotal);
        $this->assertSame('924.00', $ligne->fresh()->price);
    }

    public function test_a_custom_currency_converts_too(): void
    {
        $menu = $this->menu();
        $this->taux('$HT', 5);
        $plat = $this->article($menu, ['price' => 100, 'currency' => '$HT']);

        $this->assertSame('500.00', $this->commander($menu, [['id' => $plat->id, 'qty' => 1]])->subtotal);
    }

    public function test_without_an_active_rate_the_item_is_never_sold_at_its_raw_price(): void
    {
        $menu = $this->menu();
        $r = $this->taux('USD', 132);
        $steak = $this->article($menu, ['currency' => 'USD']);
        app(CurrencyRateService::class)->toggle($r);

        $this->expectException(OrderRuleException::class);
        $this->expectExceptionMessage('currency_rate_missing');
        $this->commander($menu, [['id' => $steak->id, 'qty' => 1]]);
    }

    public function test_a_rate_of_another_business_is_never_used_for_my_order(): void
    {
        $menu = $this->menu('t-1');
        $this->taux('USD', 132, 't-2');
        $steak = $this->article($menu, ['currency' => 'USD']);

        $this->expectExceptionMessage('currency_rate_missing');
        $this->commander($menu, [['id' => $steak->id, 'qty' => 1]]);
    }

    public function test_the_public_endpoint_explains_a_missing_rate(): void
    {
        $menu = $this->menu();
        $steak = $this->article($menu, ['currency' => 'USD']);

        $this->postJson(route('tagtoa.menu.order', $menu->alias), ['items' => [['id' => $steak->id, 'qty' => 1]], 'order_type' => 'dine_in'])
            ->assertStatus(422)->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Le prix d\'un article n\'est plus disponible dans la devise du menu. Rechargez la page.');
    }

    /* ---------- page publique ---------- */

    public function test_the_public_menu_shows_the_original_price_and_carts_in_the_menu_currency(): void
    {
        $menu = $this->menu();
        $this->taux('USD', 132);
        $steak = $this->article($menu, ['price' => 5, 'currency' => 'USD']);

        $html = $this->get(route('tagtoa.menu.show', $menu->alias))->assertOk()->getContent();
        $this->assertStringContainsString('$5.00', $html);
        $this->assertStringContainsString('≈ 660 G', $html);
        $this->assertStringContainsString('data-id="'.$steak->id.'" data-name="Steak" data-price="660"', $html);
    }

    public function test_without_rate_the_public_menu_shows_the_item_as_unavailable(): void
    {
        $menu = $this->menu();
        $steak = $this->article($menu, ['currency' => 'USD']);

        $html = $this->get(route('tagtoa.menu.show', $menu->alias))->assertOk()->getContent();
        $this->assertStringContainsString('Indisponible', $html);
        $this->assertStringNotContainsString('data-id="'.$steak->id.'"', $html, 'Pas de bouton « Ajouter ».');
    }

    /* ---------- caisse ---------- */

    private function caisse(): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'], ['currency' => 'HTG', 'is_active' => true]);
    }

    public function test_the_pos_sells_a_foreign_menu_item_at_the_converted_price(): void
    {
        $this->patron();
        $menu = $this->menu();
        $this->taux('USD', 132);
        $steak = $this->article($menu, ['price' => 5, 'cost_price' => 3, 'currency' => 'USD']);

        $ligne = collect(app(PosCatalog::class)->sellable('t-1'))->firstWhere('ref', 'menu:'.$steak->id);
        $this->assertSame(660.0, $ligne['price']);

        $vente = app(PosService::class)->recordSale($this->caisse(), ['items' => [['ref' => 'menu:'.$steak->id, 'qty' => 1, 'price' => 5]]]);
        $l = $vente->items()->sole();
        $this->assertEquals(660, (float) $l->price, 'Jamais 5 G pour 5 $.');
        $this->assertEquals(396, (float) $l->cost_price, 'Le prix d\'achat est converti lui aussi.');
    }

    public function test_the_pos_does_not_offer_a_foreign_item_without_rate(): void
    {
        $this->patron();
        $menu = $this->menu();
        $steak = $this->article($menu, ['currency' => 'USD']);

        $this->assertNull(collect(app(PosCatalog::class)->sellable('t-1'))->firstWhere('ref', 'menu:'.$steak->id));
    }

    public function test_menu_to_pos_sync_converts_and_deactivates_without_rate(): void
    {
        $this->patron();
        $menu = $this->menu();
        $r = $this->taux('USD', 132);
        $steak = $this->article($menu, ['price' => 5, 'currency' => 'USD']);
        $sync = app(MenuProductSync::class);

        $sync->sync('t-1', $this->caisse()->id);
        $p = Product::where('menu_item_id', $steak->id)->sole();
        $this->assertEquals(660, (float) $p->price);
        $this->assertTrue((bool) $p->is_active);

        app(CurrencyRateService::class)->toggle($r);
        $sync->sync('t-1', $this->caisse()->id);
        $this->assertFalse((bool) $p->fresh()->is_active, 'Sans taux : retiré de la caisse, pas vendu au prix brut.');
    }

    public function test_the_barcode_scanner_announces_the_converted_price_or_refuses(): void
    {
        $this->patron();
        $menu = $this->menu();
        $r = $this->taux('USD', 132);
        $steak = $this->article($menu, ['price' => 5, 'currency' => 'USD']);
        app(\Modules\Tagtoa\App\Services\Catalog\ProductCodes::class)->attach('t-1', 'menu:'.$steak->id, '5449000000996');

        $this->postJson(route('tagtoa.catalog.scan'), ['code' => '5449000000996'])
            ->assertOk()->assertJsonPath('found', true)->assertJsonPath('article.price', 660);

        app(CurrencyRateService::class)->toggle($r);
        $this->postJson(route('tagtoa.catalog.scan'), ['code' => '5449000000996'])
            ->assertOk()->assertJsonPath('found', false)->assertJsonPath('reason', 'currency_rate_missing');
    }

        public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000185_create_tagtoa_currency_rates.php';
        $mig->up();
        $mig->down();
        $this->assertFalse(Schema::hasTable('tagtoa_currency_rates'));
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_items', 'currency'));
        $mig->up();
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_order_items', 'rate_used'));
    }
}
