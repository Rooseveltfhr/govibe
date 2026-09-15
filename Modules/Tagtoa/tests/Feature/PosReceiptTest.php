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
 * LE REÇU DE CAISSE — un rouleau de 58 mm, pas une feuille A4.
 *
 * Ce que le client emporte est la seule pièce qui fasse foi au comptoir. Il
 * doit donc porter TROIS choses, et ce fichier garde les trois :
 *
 *   1. chez QUI il a acheté — nom, adresse, téléphone du commerce ;
 *   2. TOUT ce qu'il a pris — pas une ligne de moins ;
 *   3. la règle de la maison — « pas de retour sur ces produits », écrite
 *      AVANT la vente, pas annoncée après.
 *
 * Et il doit sortir à la bonne largeur : sans `@page`, le navigateur imprime en
 * A4 et le ticket part au milieu d'une feuille blanche, sur deux pages.
 */
class PosReceiptTest extends TestCase
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
            'id'         => 't-1', 'account_id' => 't-1',
            'name'       => 'Chez Wilner',
            'type'       => 'bar', 'currency' => 'HTG', 'is_active' => true,
            'address'    => '12, rue Capois, Port-au-Prince',
            'phone'      => '+509 3456 7890',
            'tax_number' => 'NIF-123456789',
        ], $attrs));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse 1'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    /** Une commande de trois articles différents, comme une vraie. */
    private function vente(string $tenantId = 't-1', string $ref = 'V-4242'): Sale
    {
        $sale = Sale::create([
            'terminal_id' => $this->caisse($tenantId)->id,
            'reference'   => $ref,
            'subtotal'    => 950, 'discount' => 0,
            'tax_base'    => 950, 'tax_total' => 0, 'total' => 950,
            'currency'    => 'HTG',
            'payments'    => [['method' => 'cash', 'amount' => 950]],
            'status'      => 1, 'sold_at' => now(),
        ]);

        foreach ([['Prestige biere', 250, 2], ['COCA COLA', 200, 1], ['Magi djondjon', 250, 1]] as [$nom, $prix, $q]) {
            SaleItem::create([
                'sale_id' => $sale->id, 'source' => 'pos', 'name' => $nom,
                'price' => $prix, 'qty' => $q, 'line_total' => $prix * $q,
            ]);
        }

        return $sale->load('items');
    }

    private function recu(Sale $sale): string
    {
        return $this->get(route('tagtoa.pos.ticket', $sale->id))->assertOk()->getContent();
    }

    /* ==================================================================
       1. CHEZ QUI
       ================================================================== */

    public function test_the_receipt_carries_the_shop_not_just_the_till(): void
    {
        // Un client qui revient contester présente son ticket. « Caisse 1 » ne
        // lui dit pas chez qui il a acheté, ni comment nous joindre.
        $this->commerce();
        $this->patron();
        $html = $this->recu($this->vente());

        $this->assertStringContainsString('Chez Wilner', $html);
        $this->assertStringContainsString('12, rue Capois, Port-au-Prince', $html);
        $this->assertStringContainsString('+509 3456 7890', $html);
        $this->assertStringContainsString('NIF-123456789', $html);
    }

    public function test_a_shop_without_an_address_still_prints_a_receipt(): void
    {
        // La plupart des marchands remplissent leur fiche plus tard. Un reçu qui
        // planterait faute d'adresse rendrait la caisse inutilisable le premier
        // jour — celui où il faut justement qu'elle marche.
        $this->commerce(['address' => null, 'phone' => null, 'tax_number' => null]);
        $this->patron();
        $html = $this->recu($this->vente());

        $this->assertStringContainsString('Chez Wilner', $html);
        $this->assertStringContainsString('950', $html);
    }

    /* ==================================================================
       2. TOUT CE QU'IL A PRIS
       ================================================================== */

    public function test_the_receipt_carries_every_line_of_the_order(): void
    {
        // Un reçu qui oublie une ligne est pire que pas de reçu : c'est une
        // contestation garantie, et le marchand n'a rien pour trancher.
        $this->commerce();
        $this->patron();
        $sale = $this->vente();
        $html = $this->recu($sale);

        $this->assertCount(3, $sale->items);
        foreach ($sale->items as $it) {
            $this->assertStringContainsString($it->name, $html,
                "La ligne « {$it->name} » manque au reçu.");
            $this->assertStringContainsString(number_format($it->line_total, 2), $html);
        }
    }

    /* ==================================================================
       3. LA RÈGLE DE LA MAISON
       ================================================================== */

    public function test_the_shop_owner_word_is_printed_at_the_bottom(): void
    {
        // C'est là que se règle une contestation : ce qui est imprimé sur le
        // ticket que le client TIENT fait foi. Une règle annoncée après la
        // vente ne vaut rien.
        $this->commerce(['receipt_footer' => "Mèsi paske w chwazi nou.\nNou pa aksepte retou sou pwodwi sa yo."]);
        $this->patron();
        $html = $this->recu($this->vente());

        $this->assertStringContainsString('Mèsi paske w chwazi nou.', $html);
        $this->assertStringContainsString('Nou pa aksepte retou sou pwodwi sa yo.', $html);
    }

    public function test_a_shop_that_wrote_nothing_still_gets_a_polite_receipt(): void
    {
        $this->commerce(['receipt_footer' => null]);
        $this->patron();

        $this->assertStringContainsString('Merci de votre confiance', $this->recu($this->vente()));
    }

    public function test_the_owner_word_is_escaped_not_executed(): void
    {
        // Le marchand saisit ce texte lui-même, mais un compte volé pourrait y
        // glisser du script — et le reçu s'ouvre dans le navigateur du patron.
        $this->commerce(['receipt_footer' => '<script>alert(1)</script>']);
        $this->patron();
        $html = $this->recu($this->vente());

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /* ==================================================================
       LA LARGEUR — 58 mm, la taille des rouleaux vendus en Haïti
       ================================================================== */

    public function test_the_receipt_is_sized_for_a_thermal_roll(): void
    {
        // GARDE. Sans `@page`, le navigateur imprime en A4 : le ticket sort au
        // milieu d'une feuille blanche, sur deux pages, et l'imprimante
        // thermique le coupe net à droite. Rien n'échoue — on ne s'en aperçoit
        // qu'en tenant le papier.
        $this->commerce();
        $this->patron();
        $html = $this->recu($this->vente());

        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*size:\s*58mm\s+auto/s', $html,
            'Le reçu doit déclarer une page de 58 mm de large.');
        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*margin:\s*0/s', $html,
            'Une marge de page pousse le ticket hors de la zone imprimable.');

        // Et les boutons d'écran ne doivent pas consommer du papier thermique.
        $this->assertMatchesRegularExpression('/@media\s*print\s*\{.*\.barre\{display:none\}/s', $html);
    }

    /* ==================================================================
       LE CLOISONNEMENT
       ================================================================== */

    public function test_a_receipt_of_another_shop_is_not_reachable(): void
    {
        $this->commerce();
        $autre = $this->vente('t-voisin', 'V-9999');

        $this->patron('t-1');
        $this->get(route('tagtoa.pos.ticket', $autre->id))->assertNotFound();
        $this->get(route('tagtoa.pos.receipt', 'V-9999'))->assertNotFound();
    }

    public function test_the_till_prints_by_reference_because_that_is_all_it_knows(): void
    {
        // La caisse n'a que la référence qu'elle vient d'afficher. Sans ce
        // chemin, son bouton « Imprimer » ne pouvait qu'imprimer l'écran de
        // confirmation : une page A4 presque blanche, avec les boutons dessus.
        $this->commerce();
        $this->patron();
        $this->vente('t-1', 'V-4242');

        $html = $this->get(route('tagtoa.pos.receipt', 'V-4242'))->assertOk()->getContent();

        $this->assertStringContainsString('V-4242', $html);
        $this->assertStringContainsString('Chez Wilner', $html);
        $this->assertStringContainsString('Prestige biere', $html);
    }
}
