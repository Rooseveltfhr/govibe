<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — `table_label` était du texte libre tapé par le client :
| rien n'empêchait d'écrire n'importe quel numéro, la cuisine pouvait
| porter un plat à la mauvaise table. Une TABLE vérifiée porte un code que
| seul un QR/NFC imprimé connaît ; le client le scanne, il ne le tape
| jamais, et le serveur IMPOSE ce nom — jamais un texte du client.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Table;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuVerifiedTableTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create([
            'tenant_id' => $tenantId, 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true,
            // $canOrder (show.blade.php) exige un WhatsApp : sans lui, le
            // formulaire de commande — et donc l'indicateur de table — ne
            // s'affiche pas du tout, quel que soit le contenu du menu.
            'whatsapp' => '+509 3000 0000',
        ]);
    }

    /** Un item visible, sans quoi la section "le menu arrive bientôt" masque tout le formulaire. */
    private function withVisibleItem(Menu $menu): Menu
    {
        $this->item($menu);

        return $menu;
    }

    private function item(Menu $menu): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Plat', 'price' => 100, 'is_available' => true]);
    }

    /* ------------------------------------------------------------------
       Gestion des tables (dashboard, propriétaire).
       ------------------------------------------------------------------ */

    public function test_the_owner_can_create_a_table_with_an_auto_generated_code(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->post(route('tagtoa.menu.dashboard.tables.store', $menu->id), ['label' => 'Table 4'])
            ->assertRedirect();

        $table = Table::where('menu_id', $menu->id)->first();
        $this->assertSame('Table 4', $table->label);
        $this->assertNotEmpty($table->code);
        $this->assertTrue($table->is_active);
    }

    public function test_a_foreign_tenant_cannot_create_a_table_on_another_commerces_menu(): void
    {
        $this->patron('t-1');
        $other = $this->menu('t-2');

        $this->post(route('tagtoa.menu.dashboard.tables.store', $other->id), ['label' => 'Table 1'])
            ->assertNotFound();
    }

    public function test_deleting_a_table_removes_it(): void
    {
        $this->patron();
        $menu = $this->menu();
        $table = $menu->tables()->create(['tenant_id' => 't-1', 'label' => 'T1', 'code' => Table::generateCode()]);

        $this->delete(route('tagtoa.menu.dashboard.tables.destroy', [$menu->id, $table->id]))->assertRedirect();

        $this->assertSame(0, Table::whereKey($table->id)->count());
    }

    public function test_two_tables_never_share_a_code(): void
    {
        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $codes[] = Table::generateCode();
        }
        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    /* ------------------------------------------------------------------
       Le public qui scanne un QR de table.
       ------------------------------------------------------------------ */

    public function test_scanning_a_valid_table_qr_shows_the_fixed_table_on_the_public_page(): void
    {
        $menu = $this->withVisibleItem($this->menu());
        $table = $menu->tables()->create(['tenant_id' => 't-1', 'label' => 'Terrasse 2', 'code' => Table::generateCode()]);

        $html = $this->get('/menu/'.$menu->alias.'?t='.$table->code)->assertOk()->getContent();

        $this->assertStringContainsString('Terrasse 2', $html);
        $this->assertStringNotContainsString('N° table (optionnel)', $html);
    }

    public function test_without_a_table_code_the_free_text_field_still_works(): void
    {
        $menu = $this->withVisibleItem($this->menu());

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString('N° table (optionnel)', $html);
    }

    public function test_an_unknown_table_code_is_ignored_on_the_public_page(): void
    {
        $menu = $this->withVisibleItem($this->menu());

        // Un code inconnu ne doit ni planter la page ni afficher une fausse table.
        $html = $this->get('/menu/'.$menu->alias.'?t=INCONNU')->assertOk()->getContent();

        $this->assertStringContainsString('N° table (optionnel)', $html);
    }

    public function test_the_table_lookup_is_never_shared_across_visitors_through_the_page_cache(): void
    {
        // Le rendu de $menu/$categories est mis en cache 20s ; la résolution
        // de la table ne doit JAMAIS voyager dans ce cache, sinon le premier
        // visiteur imposerait sa table à tous les suivants.
        $menu = $this->withVisibleItem($this->menu());
        $a = $menu->tables()->create(['tenant_id' => 't-1', 'label' => 'Table A', 'code' => Table::generateCode()]);
        $b = $menu->tables()->create(['tenant_id' => 't-1', 'label' => 'Table B', 'code' => Table::generateCode()]);

        $htmlA = $this->get('/menu/'.$menu->alias.'?t='.$a->code)->assertOk()->getContent();
        $htmlB = $this->get('/menu/'.$menu->alias.'?t='.$b->code)->assertOk()->getContent();

        $this->assertStringContainsString('Table A', $htmlA);
        $this->assertStringContainsString('Table B', $htmlB);
        $this->assertStringNotContainsString('Table B', $htmlA);
        $this->assertStringNotContainsString('Table A', $htmlB);
    }

    /* ------------------------------------------------------------------
       La commande : le code de table IMPOSE le nom, jamais le client.
       ------------------------------------------------------------------ */

    public function test_a_valid_table_code_imposes_its_label_ignoring_client_supplied_text(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);
        $table = $menu->tables()->create(['tenant_id' => 't-1', 'label' => 'Table 7', 'code' => Table::generateCode()]);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'table_code' => $table->code,
            // Une tentative de manipulation : le client envoie un AUTRE nom.
            'table_label' => 'Table 99 — pas la mienne',
        ]);

        $this->assertSame('Table 7', $order->table_label);
    }

    public function test_an_invalid_table_code_rejects_the_order_instead_of_silently_dropping_it(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_table');

        app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'table_code' => 'CODE-INEXISTANT',
        ]);
    }

    public function test_a_disabled_table_code_is_treated_as_invalid(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);
        $table = $menu->tables()->create(['tenant_id' => 't-1', 'label' => 'T1', 'code' => Table::generateCode(), 'is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_table');

        app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'table_code' => $table->code,
        ]);
    }

    public function test_a_table_code_from_another_menu_is_rejected(): void
    {
        $mine = $this->menu('t-1');
        $other = $this->menu('t-2');
        $item = $this->item($mine);
        $leur = $other->tables()->create(['tenant_id' => 't-2', 'label' => 'T1', 'code' => Table::generateCode()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_table');

        app(MenuOrderService::class)->placeOrder($mine, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'table_code' => $leur->code,
        ]);
    }

    public function test_without_any_table_code_the_free_text_label_still_works(): void
    {
        // Régression : un menu sans tables configurées doit continuer à
        // accepter le texte libre comme avant.
        $menu = $this->menu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'table_label' => 'Table 3',
        ]);

        $this->assertSame('Table 3', $order->table_label);
    }

    public function test_the_public_endpoint_translates_the_invalid_table_error_without_a_500(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $response = $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'table_code' => 'CODE-INEXISTANT',
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
    }
}
