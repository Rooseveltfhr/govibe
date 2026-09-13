<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Catalog\ProductCodes;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Résoudre un code scanné.
 *
 * Le même code-barres existe chez tous les commerçants du pays. C'est la
 * raison d'être de ces tests : scanner chez l'un ne doit JAMAIS donner
 * l'article, le prix ou le stock de l'autre.
 */
class ScanEndpointTest extends TestCase
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

    private function article(string $tenantId, array $attrs = []): Product
    {
        return app(PosCatalog::class)->save($this->caisse($tenantId), array_merge([
            'name' => 'Coca', 'price' => 75, 'cost_price' => 60, 'stock' => 20, 'is_active' => true,
        ], $attrs));
    }

    private function scan(string $code)
    {
        return $this->postJson(route('tagtoa.catalog.scan'), ['code' => $code]);
    }

    /* ------------------------------------------------------------------
       Le cas normal.
       ------------------------------------------------------------------ */

    public function test_a_registered_code_gives_its_article(): void
    {
        $this->patron();
        $coca = $this->article('t-1');
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->scan('5449000000996')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('article.name', 'Coca')
            // JSON ne distingue pas 75 de 75.0 : on compare la valeur, pas le type.
            ->assertJsonPath('article.price', 75)
            ->assertJsonPath('article.ref', 'pos:'.$coca->id);
    }

    public function test_a_tagtoa_label_works_without_being_registered(): void
    {
        // Étiquette rééditée, base restaurée : le code TAGTOA porte lui-même le
        // numéro de l'article, on doit pouvoir retomber dessus.
        $this->patron();
        $pate = $this->article('t-1', ['name' => 'Pâté', 'price' => 50]);

        $interne = \Modules\Tagtoa\App\Support\Catalog\Barcode::internal($pate->id);

        $this->scan($interne)->assertOk()->assertJsonPath('article.name', 'Pâté');
    }

    /* ------------------------------------------------------------------
       Le cloisonnement : la raison d'être de cet endpoint côté serveur.
       ------------------------------------------------------------------ */

    public function test_the_same_barcode_gives_each_shop_its_own_article(): void
    {
        // La bouteille de Coca porte le même numéro partout. Chacun doit
        // retrouver LE SIEN, avec SON prix.
        $this->patron('t-1');
        $chezMoi = $this->article('t-1', ['name' => 'Coca chez moi', 'price' => 75]);
        app(ProductCodes::class)->attach('t-1', 'pos:'.$chezMoi->id, '5449000000996');

        $this->patron('t-2');
        $chezLui = $this->article('t-2', ['name' => 'Coca chez le voisin', 'price' => 100]);
        app(ProductCodes::class)->attach('t-2', 'pos:'.$chezLui->id, '5449000000996');

        $this->scan('5449000000996')
            ->assertJsonPath('article.name', 'Coca chez le voisin')
            ->assertJsonPath('article.price', 100);

        $this->patron('t-1');
        $this->scan('5449000000996')
            ->assertJsonPath('article.name', 'Coca chez moi')
            // JSON ne distingue pas 75 de 75.0 : on compare la valeur, pas le type.
            ->assertJsonPath('article.price', 75);
    }

    public function test_a_code_only_the_neighbour_has_is_unknown_here(): void
    {
        $this->patron('t-2');
        $chezLui = $this->article('t-2', ['name' => 'Rhum Barbancourt']);
        app(ProductCodes::class)->attach('t-2', 'pos:'.$chezLui->id, '7640140160016');

        $this->patron('t-1');
        $this->caisse('t-1');

        $this->scan('7640140160016')
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonMissing(['name' => 'Rhum Barbancourt']);
    }

    public function test_a_tagtoa_label_from_another_shop_resolves_to_nothing(): void
    {
        // Le code interne porte un NUMÉRO d'article : sans cloisonnement, le
        // code du voisin désignerait l'article qui porte le même numéro ici.
        $this->patron('t-2');
        $chezLui = $this->article('t-2', ['name' => 'Chez le voisin']);
        $interne = \Modules\Tagtoa\App\Support\Catalog\Barcode::internal($chezLui->id);

        $this->patron('t-1');
        $this->caisse('t-1');

        $this->scan($interne)->assertOk()->assertJsonPath('found', false);
    }

    /* ------------------------------------------------------------------
       Ce qui ne doit pas sortir par cette porte.
       ------------------------------------------------------------------ */

    public function test_the_purchase_price_never_leaves_by_this_door(): void
    {
        // L'endpoint est appelé depuis une caisse, parfois tenue par quelqu'un
        // qui n'a pas à connaître la marge du commerce.
        $this->patron();
        $coca = $this->article('t-1', ['price' => 75, 'cost_price' => 60]);
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $reponse = $this->scan('5449000000996')->assertOk();

        $this->assertArrayNotHasKey('cost_price', $reponse->json('article'));
        $reponse->assertJsonMissing(['cost_price' => 60]);
        $this->assertStringNotContainsString('margin', $reponse->getContent());
    }

    /* ------------------------------------------------------------------
       Ce qu'on refuse.
       ------------------------------------------------------------------ */

    public function test_an_unknown_code_is_an_answer_not_a_failure(): void
    {
        // La caisse doit pouvoir proposer de créer l'article sans traiter ça
        // comme une panne.
        $this->patron();
        $this->caisse();

        $this->scan('9999999999994')->assertOk()->assertJsonPath('found', false)
            ->assertJsonPath('reason', 'unknown');
    }

    public function test_a_misread_barcode_designates_nothing(): void
    {
        // Un chiffre mal lu ne doit pas désigner l'article voisin : ce serait
        // encaisser le mauvais prix.
        $this->patron();
        $coca = $this->article('t-1');
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        // Même code, dernier chiffre faux : le contrôle doit le rejeter.
        $this->scan('5449000000997')->assertOk()->assertJsonPath('found', false);
    }

    public function test_a_damaged_tagtoa_label_is_refused_not_guessed(): void
    {
        $this->patron();
        $coca = $this->article('t-1');
        $bon = \Modules\Tagtoa\App\Support\Catalog\Barcode::internal($coca->id);

        // On abîme le chiffre de contrôle.
        $abime = substr($bon, 0, -1).(((int) substr($bon, -1) + 1) % 10);

        $this->scan($abime)->assertOk()->assertJsonPath('found', false);
    }

    public function test_a_stray_keystroke_is_not_a_scan(): void
    {
        $this->patron();
        $this->caisse();

        $this->scan('12')->assertOk()->assertJsonPath('reason', 'too_short');
    }

    public function test_an_empty_code_is_refused_outright(): void
    {
        $this->patron();
        $this->caisse();

        $this->postJson(route('tagtoa.catalog.scan'), ['code' => ''])
            ->assertStatus(422);
    }

    /* ------------------------------------------------------------------
       Hors ligne : le scanner doit continuer de vendre.
       ------------------------------------------------------------------ */

    public function test_the_register_carries_its_codes_so_it_scans_offline(): void
    {
        // La caisse travaille hors ligne par conception. Interroger le serveur
        // à chaque article rendrait le scanner inutilisable le jour où la
        // connexion tombe — c'est-à-dire le jour où il sert le plus.
        $this->patron();
        $coca = $this->article('t-1');
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->get(route('tagtoa.pos.register', $this->caisse()->id))
            ->assertOk()
            ->assertSee('5449000000996')
            ->assertSee('PAR_CODE', false);
    }

    public function test_the_register_never_carries_the_neighbour_codes(): void
    {
        $this->patron('t-2');
        $chezLui = $this->article('t-2', ['name' => 'Rhum Barbancourt']);
        app(ProductCodes::class)->attach('t-2', 'pos:'.$chezLui->id, '7640140160016');

        $this->patron('t-1');
        $this->article('t-1');

        $this->get(route('tagtoa.pos.register', $this->caisse('t-1')->id))
            ->assertOk()
            ->assertDontSee('7640140160016');
    }

    public function test_the_register_never_carries_purchase_prices(): void
    {
        // La page est ouverte sur un comptoir, parfois devant le client.
        $this->patron();
        $this->article('t-1', ['price' => 75, 'cost_price' => 61.5]);

        $this->get(route('tagtoa.pos.register', $this->caisse()->id))
            ->assertOk()
            ->assertDontSee('61.5');
    }
}
