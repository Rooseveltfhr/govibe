<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

/**
 * L'ÉCRAN DE CAISSE — trois défauts signalés depuis un téléphone, en salle.
 *
 *   1. Le panier était collé en bas et prenait la moitié de la hauteur, en
 *      permanence, même vide.
 *   2. La grille d'articles ne défilait pas : au-delà du sixième, les articles
 *      existaient et étaient INATTEIGNABLES. Rien n'échouait.
 *   3. Une photo introuvable affichait l'icône « image cassée » sur le bouton
 *      que le caissier doit reconnaître d'un coup d'œil.
 *
 * Chacun a sa garde ici, parce qu'aucun des trois ne se manifeste par une
 * erreur : ils ne se voient qu'en tenant le téléphone.
 */
class PosRegisterScreenTest extends TestCase
{
    use RefreshDatabase;

    private function caisse(): Terminal
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();

        return Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse 1'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    private function ecran(): string
    {
        $caisse = $this->caisse();

        app(PosCatalog::class)->save($caisse, [
            'name' => 'Prestige biere', 'price' => 250, 'stock' => 10,
            'is_active' => true, 'image_path' => 'tagtoa/pos-products/x.jpg',
        ]);

        return $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()->getContent();
    }

    /* ==================================================================
       1. Le panier quitte le bas de l'écran
       ================================================================== */

    public function test_the_cart_opens_from_an_icon_next_to_the_scanner(): void
    {
        // Les deux gestes de la caisse — scanner, voir le panier — sont côte à
        // côte en haut, et l'écran entier reste au catalogue.
        $html = $this->ecran();

        $this->assertStringContainsString('id="cartBtn"', $html);
        $this->assertStringContainsString('onclick="openCart()"', $html);

        // L'icône du panier vient APRÈS celle du code-barres : c'est ce qui a
        // été demandé, et l'ordre d'une barre d'outils se retient par la place.
        $this->assertLessThan(
            strpos($html, 'id="cartBtn"'),
            strpos($html, 'id="scanBtn"'),
            'Le panier doit être à côté du code-barres, après lui.'
        );
    }

    public function test_the_cart_carries_a_counter_because_a_closed_cart_is_forgotten(): void
    {
        // Panier fermé, la bulle est le SEUL signe qu'un article est entré :
        // sans elle, on rescanne le même article en croyant l'avoir raté.
        $html = $this->ecran();

        $this->assertStringContainsString('id="cartN"', $html);
        $this->assertStringContainsString("document.getElementById('cartN')", $html);
    }

    public function test_the_cart_is_not_pinned_open_over_the_catalogue(): void
    {
        // GARDE. L'ancienne feuille collait le panier en bas avec 48vh de
        // hauteur : la moitié du téléphone perdue en permanence, même panier
        // vide. Il se glisse maintenant hors de l'écran tant qu'on ne l'ouvre pas.
        $html = $this->ecran();

        $this->assertStringContainsString('transform:translateY(100%)', $html,
            'Le panier doit être escamoté tant qu\'on ne l\'ouvre pas.');
        $this->assertStringContainsString('.cart.show{transform:translateY(0)}', $html);
    }

    /* ==================================================================
       2. La grille défile vraiment
       ================================================================== */

    public function test_the_product_grid_can_actually_scroll_on_a_phone(): void
    {
        // GARDE, et c'est la subtile. `overflow-y:auto` ne suffit PAS : il faut
        // aussi que la grille ait une hauteur à ne pas dépasser. Dans un
        // conteneur flex, cela s'écrit `flex:1` ET `min-height:0` — sans le
        // second, un enfant flex refuse de rétrécir sous son contenu, la grille
        // pousse sous le pli, et `body{overflow:hidden}` coupe le reste. Les
        // articles au-delà du sixième redeviennent inatteignables, en silence.
        $html = $this->ecran();

        $this->assertStringContainsString('overflow-y:auto', $html);
        $this->assertMatchesRegularExpression('/\.grid\{flex:1 1 auto;min-height:0\}/', $html,
            'Sans min-height:0, la grille ne défile pas et personne ne le voit.');
    }

    /* ==================================================================
       3. Une photo qui manque ne casse pas le bouton
       ================================================================== */

    public function test_a_missing_photo_falls_back_to_the_initial(): void
    {
        // Une icône « image cassée » sur un bouton de caisse est pire que pas
        // de photo : elle ne se reconnaît pas d'un coup d'œil, et c'est
        // exactement ce qu'on demande au caissier en pleine affluence.
        $html = $this->ecran();

        $this->assertStringContainsString('onerror="tagtoaPhotoCassee(this)"', $html);
        $this->assertStringContainsString('data-initiale="P"', $html);
        $this->assertStringContainsString('function tagtoaPhotoCassee', $html);
    }

    /* ==================================================================
       4. Imprimer ouvre le VRAI reçu
       ================================================================== */

    public function test_printing_opens_the_real_receipt_not_the_current_screen(): void
    {
        // GARDE. `window.print()` imprimait CETTE page : une feuille A4 presque
        // blanche avec les boutons dessus, sur deux pages. C'est ce qui sortait
        // de l'imprimante.
        $html = $this->ecran();

        $this->assertStringContainsString('onclick="imprimerRecu()"', $html);
        $this->assertStringContainsString('/tagtoa/pos/receipt/', $html,
            'La caisse doit connaître l\'adresse du reçu.');
        $this->assertStringNotContainsString('onclick="print()"', $html,
            'Imprimer l\'écran de caisse produit une page A4 presque vide.');
    }

    /* ==================================================================
       5. Scanner : un ajout se VOIT, et rend la main
       ================================================================== */

    public function test_a_scan_speaks_inside_the_scanner_not_behind_it(): void
    {
        // LE défaut signalé : « ça fait le son quand le code passe devant la
        // caméra, puis plus rien ». L'article ÉTAIT ajouté — mais la caméra
        // couvre tout l'écran et le panier est une fenêtre fermée : rien de ce
        // qui changeait n'était visible. Un travail fait sans preuve ressemble
        // à un travail non fait, et le caissier rescanne ou renonce.
        $html = $this->ecran();

        $this->assertStringContainsString('function direDansScanner', $html);
        $this->assertStringContainsString('TagtoaScanner.say(', $html,
            'Le scanner doit dire lui-même ce qu\'il vient d\'ajouter.');
    }

    public function test_a_successful_scan_adds_then_hands_control_back(): void
    {
        // Demandé explicitement : scanner ajoute l'article, puis on ressort
        // vers l'écran où se trouve le bouton scanner.
        $html = $this->ecran();

        $this->assertStringContainsString('function ajouterEtRendreLaMain', $html);
        $this->assertStringContainsString('TagtoaScanner.close();', $html);
        // La confirmation s'affiche AVANT la fermeture : fermer d'abord
        // effacerait la seule preuve que le caissier aura vue.
        $this->assertLessThan(
            strpos($html, 'TagtoaScanner.close();'),
            strpos($html, 'if(direDansScanner(texte)){'),
            'Le message doit précéder la fermeture.'
        );
    }

    public function test_an_unknown_code_keeps_the_camera_open(): void
    {
        // Une étiquette abîmée se revise. Refermer après chaque échec
        // obligerait à rouvrir le scanner pour chaque tentative.
        $html = $this->ecran();

        $this->assertMatchesRegularExpression(
            '/TagtoaScanner\.reject\(\);\s*\n\s*var inconnu/',
            $html,
            'Un code inconnu signale l\'erreur sans fermer la caméra.'
        );
    }

    public function test_the_scanner_promises_only_what_it_does(): void
    {
        // L'ancien texte annonçait « chaque lecture ajoute l'article au
        // panier » alors que l'écran se referme après la première : la
        // surprise se prend pour une panne.
        $html = $this->ecran();

        $this->assertStringNotContainsString('Chaque lecture ajoute', $html);
        $this->assertStringContainsString('l\'écran se referme', $html);
    }

    public function test_the_cashier_never_reads_an_escaped_apostrophe(): void
    {
        // GARDE, trouvée au navigateur. `{{ }}` échappe POUR LE HTML : dans un
        // <script>, « l'article » devient « l&#039;article » — et c'est ce que
        // le caissier lit à l'écran. Les chaînes du script passent donc par
        // @js(), qui encode pour JavaScript.
        //
        // Dans un ATTRIBUT HTML (title, placeholder), &#039; est correct et
        // s'affiche bien : on ne regarde que le bloc <script>.
        $html = $this->ecran();

        $debut = strpos($html, '<script>'.PHP_EOL.'/* Photo introuvable');
        $this->assertNotFalse($debut, 'Le script de la caisse a changé de forme : garde à réviser.');

        $script = substr($html, $debut);

        $this->assertStringNotContainsString('&#039;', $script,
            'Une chaîne du script est échappée pour le HTML : le caissier lira « l&#039;article ».');
        $this->assertStringNotContainsString('&quot;', $script);
    }

    public function test_an_offline_sale_says_so_instead_of_opening_a_broken_page(): void
    {
        // Hors ligne la vente n'est pas encore en base : il n'y a rien à
        // imprimer. On le dit, plutôt que d'ouvrir une page en erreur devant
        // le client.
        $html = $this->ecran();

        $this->assertStringContainsString('derniereRef=(srv&&srv.reference)?srv.reference:null;', $html,
            'La référence ne doit être retenue que si le serveur l\'a donnée.');
        $this->assertStringContainsString('if(!derniereRef)', $html);
    }
}
