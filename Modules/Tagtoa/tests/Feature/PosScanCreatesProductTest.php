<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — la caisse crée un article pour un code qu'elle ne connaît pas
|--------------------------------------------------------------------------
| Avant ce correctif, scanner un code bar inconnu à la caisse ne faisait
| qu'un bip d'erreur : rien de plus. Le geste qui existait déjà côté
| réception de carton (PosController::scanProduct — un article provisoire,
| inactif, créé aussitôt pour être nommé/tarifé ensuite) n'était pas
| déclenchable depuis la caisse elle-même, alors que c'est là que le
| caissier tient réellement le produit en main.
|
| La caisse route désormais vers CE MÊME point d'entrée déjà testé
| (PosMenuTest::test_scanning_an_unknown_code_creates_and_saves_the_article)
| — ces tests vérifient seulement que l'écran de caisse le CÂBLE bien, pas
| que l'endpoint lui-même fonctionne (déjà couvert ailleurs).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

class PosScanCreatesProductTest extends TestCase
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

    public function test_the_register_points_its_create_url_at_the_terminal_s_own_scan_endpoint(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse 1'],
            ['currency' => 'HTG', 'is_active' => true]);

        $html = $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()->getContent();

        $attendu = route('tagtoa.pos.products.scan', $caisse->id);
        $this->assertStringContainsString('var CREATE_URL = "'.$attendu.'"', $html);
    }

    public function test_an_unknown_code_calls_the_creation_endpoint_instead_of_only_erroring(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('function creerArticlePourCode(code)', $html);
        $this->assertStringContainsString('fetch(CREATE_URL', $html);

        // L'ancien comportement (bip d'erreur seul, sans rien créer) ne doit
        // plus être ce qui arrive quand le catalogue local ET le serveur ne
        // reconnaissent pas le code : c'est creerArticlePourCode qui reçoit
        // la main, pas un message final.
        $this->assertMatchesRegularExpression(
            '/creerArticlePourCode\(code\);\s*\}\)/',
            $html
        );
    }

    public function test_a_created_article_is_never_silently_added_to_the_cart(): void
    {
        $html = $this->ecran();

        // Le bloc qui gère la réponse de création n'appelle jamais add(...) :
        // un article à prix zéro encaissé, c'est une vente ratée.
        preg_match('/function creerArticlePourCode\(code\)\{.*?\n\}/s', $html, $m);
        $this->assertNotEmpty($m, 'creerArticlePourCode introuvable dans le HTML rendu.');
        $this->assertStringNotContainsString('add(', $m[0]);
    }
}
