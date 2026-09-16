<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleItem;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\Tests\TestCase;

/**
 * L'IMPRESSION BLUETOOTH — un second chemin, honnête sur ce qu'il couvre.
 *
 * Web Bluetooth ne parle QU'AU BLE (GATT) : ce n'est pas une limite de TAGTOA,
 * c'est la norme elle-même qui exclut le Bluetooth classique (SPP) d'une page
 * web. La majorité des imprimantes 58 mm bon marché vendues en Haïti sont en
 * SPP — elles resteront servies par `window.print()`, chemin qui existait déjà.
 *
 * Ce fichier garde deux choses : que le point JSON dise EXACTEMENT ce que dit
 * le reçu HTML (même client, même chiffres, un seul endroit qui les calcule),
 * et que l'écran ne cache pas la limite à qui a la mauvaise imprimante.
 */
class PosBtPrinterTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    private function commerce(array $attrs = []): Business
    {
        return Business::create(array_merge([
            'id' => 't-1', 'account_id' => 't-1', 'name' => 'Chez Wilner',
            'type' => 'bar', 'currency' => 'HTG', 'is_active' => true,
            'address' => '12, rue Capois', 'phone' => '+509 3456 7890',
        ], $attrs));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse 1'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    private function vente(string $tenantId = 't-1', string $ref = 'V-4242'): Sale
    {
        $sale = Sale::create([
            'terminal_id' => $this->caisse($tenantId)->id, 'reference' => $ref,
            'subtotal' => 500, 'discount' => 0, 'tax_base' => 500, 'tax_total' => 50, 'total' => 550,
            'tax_label' => 'TCA', 'currency' => 'HTG',
            'payments' => [['method' => 'cash', 'amount' => 550]],
            'status' => 1, 'sold_at' => now(),
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'source' => 'pos', 'name' => 'Prestige biere',
            'price' => 250, 'qty' => 2, 'line_total' => 500]);

        return $sale->load('items');
    }

    /* ==================================================================
       LE POINT JSON — la même vérité que le reçu HTML
       ================================================================== */

    public function test_the_data_endpoint_carries_pre_formatted_amounts(): void
    {
        // Aucun calcul ne doit se refaire côté imprimante : les montants
        // sortent déjà formatés, exactement comme sur le reçu HTML.
        $this->commerce();
        $this->patron();
        $this->vente();

        $json = $this->get(route('tagtoa.pos.receipt.data', 'V-4242'))->assertOk()->json();

        $this->assertSame('Chez Wilner', $json['business']['name']);
        $this->assertSame('12, rue Capois', $json['business']['address']);
        $this->assertSame('V-4242', $json['reference']);
        $this->assertCount(1, $json['items']);
        $this->assertSame('Prestige biere', $json['items'][0]['name']);
        $this->assertSame('550 G', $json['total']);
        $this->assertSame('TCA', $json['tax_label']);
        $this->assertSame('50 G', $json['tax_total']);
    }

    public function test_the_data_endpoint_matches_the_html_receipt_exactly(): void
    {
        // GARDE. Un ticket Bluetooth qui recalculerait son propre affichage
        // finirait, un jour, par ne plus dire la même chose que le reçu HTML
        // du même client — la pire contradiction possible au comptoir.
        $this->commerce();
        $this->patron();
        $this->vente();

        $html = $this->get(route('tagtoa.pos.receipt', 'V-4242'))->assertOk()->getContent();
        $json = $this->get(route('tagtoa.pos.receipt.data', 'V-4242'))->assertOk()->json();

        $this->assertStringContainsString($json['total'], $html);
        $this->assertStringContainsString($json['tax_total'], $html);
        $this->assertStringContainsString($json['items'][0]['line_total'], $html);
    }

    public function test_a_shop_with_no_footer_still_gets_a_polite_one(): void
    {
        $this->commerce(['receipt_footer' => null]);
        $this->patron();
        $this->vente();

        $json = $this->get(route('tagtoa.pos.receipt.data', 'V-4242'))->assertOk()->json();

        $this->assertStringContainsString('Merci', $json['footer']);
    }

    public function test_a_sale_with_no_discount_or_tax_omits_them_rather_than_printing_zero(): void
    {
        // Un ticket qui imprime « Remise : 0 G » sur chaque vente est un
        // ticket que personne ne lit plus.
        $this->commerce();
        $this->patron();
        $sale = Sale::create([
            'terminal_id' => $this->caisse()->id, 'reference' => 'V-1',
            'subtotal' => 100, 'discount' => 0, 'tax_base' => 100, 'tax_total' => 0, 'total' => 100,
            'currency' => 'HTG', 'payments' => [], 'status' => 1, 'sold_at' => now(),
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'source' => 'pos', 'name' => 'Article',
            'price' => 100, 'qty' => 1, 'line_total' => 100]);

        $json = $this->get(route('tagtoa.pos.receipt.data', 'V-1'))->assertOk()->json();

        $this->assertNull($json['discount']);
        $this->assertNull($json['tax_total']);
        $this->assertNull($json['tax_label']);
    }

    /* ==================================================================
       LE CLOISONNEMENT — même exigence que le reçu HTML
       ================================================================== */

    public function test_another_shops_receipt_data_is_not_reachable(): void
    {
        $this->commerce();
        $this->vente('t-voisin', 'V-9999');

        $this->patron('t-1');
        $this->get(route('tagtoa.pos.receipt.data', 'V-9999'))->assertNotFound();
    }

    /* ==================================================================
       L'ÉCRAN — honnête sur ce qu'il couvre
       ================================================================== */

    public function test_the_screen_says_which_printers_it_does_not_cover(): void
    {
        // La demande était « fè l mache » ; la réponse honnête à une norme qui
        // exclut le Bluetooth classique est de LE DIRE, pas de fabriquer une
        // impression qui échouera en silence sur la moitié du parc.
        $this->commerce();
        $this->patron();
        $this->vente();

        $html = $this->get(route('tagtoa.pos.receipt', 'V-4242'))->assertOk()->getContent();

        $this->assertStringContainsString('tagtoa-bt-printer.js', $html);
        $this->assertStringContainsString('BLE', $html);
        // Le bloc reste caché tant que le script n'a pas confirmé la capacité
        // du navigateur : jamais un bouton qui échoue au clic sans explication.
        $this->assertMatchesRegularExpression('/\.bt\{display:none/', $html);
        $this->assertStringContainsString("TagtoaBTPrinter.available()", $html);
    }

    public function test_the_bluetooth_button_is_never_html_escaped_in_the_script(): void
    {
        // Même piège que la caisse : {{ }} échappe pour le HTML, et dans un
        // <script> l'apostrophe devient une entité que le commerçant lit telle
        // quelle.
        $this->commerce();
        $this->patron();
        $this->vente();

        $html = $this->get(route('tagtoa.pos.receipt', 'V-4242'))->assertOk()->getContent();
        $debut = strpos($html, "<script src=\"");
        $script = substr($html, strpos($html, '<script>', $debut));

        $this->assertStringNotContainsString('&#039;', $script);
        $this->assertStringNotContainsString('&quot;', $script);
    }
}
