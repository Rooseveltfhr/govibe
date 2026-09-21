<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — le reçu WhatsApp ressemble enfin à un reçu
|--------------------------------------------------------------------------
| Avant ce correctif, le bouton « Reçu » de l'écran de caisse composait un
| texte lui-même, à partir du panier EN MÉMOIRE : une liste "2x Coca = 150",
| sans nom de commerce, sans adresse, sans mot de fin — jamais le même calcul
| que le ticket imprimé (TicketController::data, déjà utilisé par le reçu
| Bluetooth).
|
| Le correctif fait composer le message à partir de CETTE MÊME source
| serveur, une fois la vente confirmée en ligne, avec un repli honnête vers
| le texte simple d'avant quand ce détail n'est pas joignable (vente
| hors-ligne, ou réseau qui lâche juste après) — jamais un bouton inerte.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

class PosWhatsAppReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse 1'],
            ['currency' => 'HTG', 'is_active' => true]);
        app(PosCatalog::class)->save($caisse, ['name' => 'Prestige', 'price' => 250, 'stock' => 10, 'is_active' => true]);

        return $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()->getContent();
    }

    public function test_the_register_points_at_the_same_receipt_data_endpoint_as_the_bluetooth_printer(): void
    {
        $html = $this->ecran();

        $attendu = route('tagtoa.pos.receipt.data', ['reference' => '__REF__']);
        $this->assertStringContainsString('var RECU_DATA_URL="'.$attendu.'"', $html);
    }

    public function test_a_confirmed_sale_fetches_the_server_receipt_before_enriching_the_whatsapp_link(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('function posterRecuWhatsApp(ref, p)', $html);
        $this->assertStringContainsString('fetch(RECU_DATA_URL', $html);
        // Le même garde que imprimerRecu() : pas de référence serveur, pas
        // d'appel réseau — la vente hors-ligne n'existe pas encore en base.
        $this->assertStringContainsString('if(!derniereRef || !navigator.onLine) return;', $html);
        $this->assertStringContainsString('posterRecuWhatsApp(ref, p);', $html);
    }

    public function test_the_whatsapp_text_carries_a_header_item_lines_and_a_footer(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('function texteRecuWhatsApp(d)', $html);
        // En-tête : nom du commerce en gras (format WhatsApp), adresse.
        $this->assertStringContainsString("'*'+((d.business && d.business.name)", $html);
        $this->assertStringContainsString('d.business.address', $html);
        // Lignes d'articles depuis la même donnée que le reçu imprimé.
        $this->assertStringContainsString("it.qty+'x '+it.name+' — '+it.line_total", $html);
        // Total en gras, et le mot de fin du commerce.
        $this->assertStringContainsString("'*' + 'Total' + ': ' + d.total + '*", $html);
        $this->assertStringContainsString('d.footer', $html);
    }

    public function test_the_button_always_carries_a_link_even_before_any_network_reply(): void
    {
        $html = $this->ecran();

        // Le texte simple est posé AVANT le fetch, pas dans son .then() : le
        // bouton n'est jamais inerte pendant que le serveur répond, et reste
        // utilisable si la vente est hors-ligne.
        $posSimple = strpos($html, "lien.href='https://wa.me/'+tel+'?text='+encodeURIComponent(simple);");
        $posFetch = strpos($html, 'fetch(RECU_DATA_URL');
        $this->assertNotFalse($posSimple);
        $this->assertNotFalse($posFetch);
        $this->assertLessThan($posFetch, $posSimple);
    }
}
