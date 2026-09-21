<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

/**
 * LE MENU MULTILINGUE — le client choisit sa langue, le marchand écrit une fois.
 *
 * LA RÈGLE QUI GOUVERNE TOUT CE FICHIER, et qui a son propre test :
 *
 *   UN MENU SANS LA MOINDRE TRADUCTION S'AFFICHE EXACTEMENT COMME AVANT.
 *
 * Aucune des cartes déjà publiées ne doit changer d'apparence le jour où ce
 * module arrive. Et surtout : il n'existe qu'UN plat, UN prix, UNE photo —
 * seul le texte a une variante par langue, jamais le reste.
 */
class MenuTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    private function menuGarni(array $traductions = []): Menu
    {
        $menu = Menu::create([
            'tenant_id' => 't-1', 'name' => 'Chez Wilner', 'alias' => 'chez-wilner',
            'currency' => 'HTG', 'is_active' => true, 'tagline' => 'Cuisine créole',
            'translations' => $traductions['menu'] ?? null,
        ]);
        $cat = Category::create([
            'menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true,
            'translations' => $traductions['category'] ?? null,
        ]);
        Item::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Griot', 'description' => 'Porc frit, bananes plantains',
            'price' => 350, 'stock' => 10, 'is_available' => true,
            'translations' => $traductions['item'] ?? null,
        ]);

        return $menu;
    }

    /* ==================================================================
       LA RÈGLE : aucune régression sur une carte déjà publiée
       ================================================================== */

    public function test_a_menu_with_no_translation_looks_exactly_as_before_in_any_language(): void
    {
        $menu = $this->menuGarni();

        foreach (['fr', 'ht', 'en', 'es'] as $langue) {
            $html = $this->get('/menu/chez-wilner?lang='.$langue)->assertOk()->getContent();

            $this->assertStringContainsString('Cuisine créole', $html);
            $this->assertStringContainsString('Plats', $html);
            $this->assertStringContainsString('Griot', $html);
            $this->assertStringContainsString('Porc frit, bananes plantains', $html);
        }
    }

    /* ==================================================================
       AVEC des traductions : la langue du client gagne
       ================================================================== */

    public function test_the_visitors_chosen_language_wins_when_a_translation_exists(): void
    {
        $menu = $this->menuGarni([
            'menu'     => ['en' => ['tagline' => 'Creole cuisine']],
            'category' => ['en' => ['name' => 'Main dishes']],
            'item'     => ['en' => ['name' => 'Fried pork', 'description' => 'Fried pork, plantains']],
        ]);

        $html = $this->get('/menu/'.$menu->alias.'?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('Creole cuisine', $html);
        $this->assertStringContainsString('Main dishes', $html);
        $this->assertStringContainsString('Fried pork', $html);
        $this->assertStringContainsString('Fried pork, plantains', $html);
        // Le texte de base ne doit plus apparaître comme nom du plat — sinon
        // le client verrait les deux langues mélangées.
        $this->assertStringNotContainsString('>Griot<', $html);
    }

    public function test_a_language_with_no_translation_falls_back_to_the_merchants_text(): void
    {
        // Traduit en anglais seulement : un client espagnol doit tout de même
        // voir le plat, dans la langue du marchand — jamais un trou dans la carte.
        $menu = $this->menuGarni([
            'item' => ['en' => ['name' => 'Fried pork']],
        ]);

        $html = $this->get('/menu/'.$menu->alias.'?lang=es')->assertOk()->getContent();

        $this->assertStringContainsString('Griot', $html);
    }

    public function test_the_category_icon_keeps_reading_the_base_name_not_the_translation(): void
    {
        // GARDE. L'icône se déduit par mots-clés du nom DE BASE. Si elle
        // suivait la traduction, une catégorie déjà réglée dans la langue du
        // marchand changerait d'icône selon la langue du visiteur — un
        // comportement que personne n'a demandé et que personne ne
        // comprendrait.
        $menu = $this->menuGarni([
            'category' => ['en' => ['name' => 'Drinks']], // mot-clé "boisson" absent en anglais
        ]);
        Category::where('menu_id', $menu->id)->update(['name' => 'Boissons']);

        $htmlBase = $this->get('/menu/'.$menu->alias.'?lang=fr')->assertOk()->getContent();
        $htmlEn   = $this->get('/menu/'.$menu->alias.'?lang=en')->assertOk()->getContent();

        // La même icône déduite de « Boissons » doit apparaître dans les deux
        // langues — extraite juste avant le nom affiché dans le fil de la page.
        preg_match('/fa-solid (fa-\S+)"><\/i>\s*\n?\s*Boissons/', $htmlBase, $mBase);
        preg_match('/fa-solid (fa-\S+)"><\/i>\s*\n?\s*Drinks/', $htmlEn, $mEn);

        $this->assertNotEmpty($mBase, 'Icône introuvable en français.');
        $this->assertNotEmpty($mEn, 'Icône introuvable en anglais.');
        $this->assertSame($mBase[1], $mEn[1], 'L\'icône a changé selon la langue du visiteur.');
    }

    /* ==================================================================
       LE MARCHAND ENREGISTRE UNE TRADUCTION
       ================================================================== */

    public function test_the_merchant_can_save_a_translation_through_the_dashboard(): void
    {
        $menu = $this->menuGarni();
        $cat = $menu->categories()->first();
        $item = $cat->items()->first();
        $this->patron();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Chez Wilner', 'currency' => 'HTG',
            'translations_sent' => 1,
            'translations' => ['en' => ['tagline' => 'Creole cuisine']],
            'cats' => [[
                'id' => $cat->id, 'name' => 'Plats',
                'translations_sent' => 1,
                'translations' => ['en' => ['name' => 'Main dishes']],
                'items' => [[
                    'id' => $item->id, 'name' => 'Griot', 'price' => 350, 'stock' => 10,
                    'translations_sent' => 1,
                    'translations' => ['en' => ['name' => 'Fried pork', 'description' => 'Pork & plantains']],
                ]],
            ]],
            'form_end' => 1,
        ])->assertRedirect();

        $menu->refresh();
        $item->refresh();
        $cat->refresh();

        $this->assertSame('Creole cuisine', $menu->translations['en']['tagline']);
        $this->assertSame('Main dishes', $cat->translations['en']['name']);
        $this->assertSame('Fried pork', $item->translations['en']['name']);
        $this->assertSame('Pork & plantains', $item->translations['en']['description']);
    }

    public function test_a_submission_without_the_marker_never_wipes_an_existing_translation(): void
    {
        // Même garde que pour les options d'un article (`options_sent`) : un
        // vieux gabarit en cache, ou un appel qui ne connaît pas ce panneau, ne
        // doit JAMAIS effacer ce qui est déjà enregistré.
        $menu = $this->menuGarni(['item' => ['en' => ['name' => 'Fried pork']]]);
        $cat = $menu->categories()->first();
        $item = $cat->items()->first();
        $this->patron();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Chez Wilner', 'currency' => 'HTG',
            // PAS de translations_sent, PAS de translations_sent sur l'article.
            'cats' => [[
                'id' => $cat->id, 'name' => 'Plats',
                'items' => [[
                    'id' => $item->id, 'name' => 'Griot', 'price' => 350, 'stock' => 10,
                ]],
            ]],
            'form_end' => 1,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('Fried pork', $item->translations['en']['name'] ?? null,
            'Une traduction existante a disparu alors que le formulaire ne l\'a pas envoyée.');
    }

    public function test_an_unknown_locale_or_field_submitted_by_a_crafted_request_is_dropped(): void
    {
        // Un client qui fabrique sa propre requête ne doit pouvoir écrire ni
        // une langue inventée, ni un champ hors de la liste autorisée (le prix
        // d'un plat ne se « traduit » pas).
        $menu = $this->menuGarni();
        $cat = $menu->categories()->first();
        $item = $cat->items()->first();
        $this->patron();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Chez Wilner', 'currency' => 'HTG',
            'cats' => [[
                'id' => $cat->id, 'name' => 'Plats',
                'translations_sent' => 1,
                'translations' => ['zz' => ['name' => 'Langue inventée']],
                'items' => [[
                    'id' => $item->id, 'name' => 'Griot', 'price' => 350, 'stock' => 10,
                    'translations_sent' => 1,
                    'translations' => ['en' => ['name' => 'Fried pork', 'price' => '9999']],
                ]],
            ]],
            'form_end' => 1,
        ])->assertRedirect();

        $cat->refresh();
        $item->refresh();

        $this->assertNull($cat->translations, 'Une langue inconnue a été enregistrée.');
        $this->assertSame(['name' => 'Fried pork'], $item->translations['en']);
        $this->assertArrayNotHasKey('price', $item->translations['en']);
        $this->assertSame('350.00', (string) $item->price, 'Le prix ne doit jamais passer par le canal des traductions.');
    }

    /* ==================================================================
       LA COMMANDE — figée dans la langue du client, pas celle du marchand
       ================================================================== */

    public function test_an_order_line_freezes_the_name_the_customer_actually_saw(): void
    {
        // Un client qui a lu « Fried pork » et commandé ne doit pas recevoir
        // une confirmation dans une langue qu'il n'a pas choisie.
        $menu = $this->menuGarni(['item' => ['en' => ['name' => 'Fried pork']]]);
        $menu->update(['ordering_enabled' => true, 'whatsapp' => '+50912345678']);
        $item = $menu->categories()->first()->items()->first();

        $reponse = $this->postJson('/menu/'.$menu->alias.'/order?lang=en', [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'client_uuid' => 'test-uuid-1',
        ])->assertOk();

        $order = \Modules\Tagtoa\App\Models\Menu\Order::where('reference', $reponse->json('reference'))->firstOrFail();

        $this->assertSame('Fried pork', $order->items->first()->name);
    }
}
