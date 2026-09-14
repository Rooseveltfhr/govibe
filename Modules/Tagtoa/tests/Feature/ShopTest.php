<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Shop\ShopItem;
use Modules\Tagtoa\App\Models\Shop\ShopOrder;
use Modules\Tagtoa\App\Models\Shop\ShopOrderItem;
use Modules\Tagtoa\App\Services\Shop\ShopService;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

/**
 * BOUTIQUE TAGTOA — le marchand commande son matériel.
 *
 * Le Smart Stand existait de bout en bout SAUF le début : comment un restaurant
 * obtient-il ses quarante stands ? Par WhatsApp, au jugé, sans trace.
 *
 * Trois règles gouvernent ce fichier :
 *   1. le prix ne vient jamais du navigateur ;
 *   2. les quantités sont ramenées à ce qu'on sait réellement expédier ;
 *   3. un double envoi ne commande qu'une fois — sinon deux cartons partent.
 */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function marchand(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function article(array $attrs = []): ShopItem
    {
        return ShopItem::create(array_merge([
            'sku' => 'STAND-'.random_int(100, 999), 'name' => 'Smart Stand A5',
            'unit_price' => 12.50, 'min_qty' => 1, 'step_qty' => 1, 'is_active' => true,
        ], $attrs));
    }

    private function service(): ShopService
    {
        return app(ShopService::class);
    }

    /* ------------------------------------------------------------------
       1. Le prix ne vient jamais du navigateur.
       ------------------------------------------------------------------ */

    public function test_the_total_is_priced_from_the_catalogue(): void
    {
        $a = $this->article(['unit_price' => 12.50]);

        $c = $this->service()->chiffrer([$a->id => 4]);

        $this->assertEquals(50.0, $c['subtotal']);
    }

    public function test_the_order_form_never_accepts_a_price(): void
    {
        // Le panier dit QUELS articles et COMBIEN — jamais combien d'argent.
        // Un champ de prix serait un bon de commande à remplir soi-même.
        foreach (['shop/index', 'shop/checkout'] as $vue) {
            $html = (string) file_get_contents(__DIR__.'/../../resources/views/'.$vue.'.blade.php');
            foreach (['name="unit_price"', 'name="total"', 'name="subtotal"', 'name="price"'] as $interdit) {
                $this->assertStringNotContainsString($interdit, $html, "$vue contient $interdit");
            }
        }
    }

    public function test_a_price_raised_later_never_rewrites_an_order(): void
    {
        // TAGTOA qui augmente le prix du stand le mois prochain ne doit pas
        // réécrire un bon de commande déjà envoyé au marchand.
        $this->marchand();
        $a = $this->article(['unit_price' => 12.50]);

        $r = $this->service()->commander([$a->id => 2], 't-1', ['idempotency_key' => 'k1']);
        $this->assertSame(ShopService::OK, $r['result']);

        $a->update(['unit_price' => 99, 'name' => 'Smart Stand A5 v2']);

        $ligne = ShopOrderItem::firstOrFail();
        $this->assertEquals(12.50, (float) $ligne->unit_price, 'Le prix est figé à la commande.');
        $this->assertSame('Smart Stand A5', $ligne->name, 'Le nom aussi.');
        $this->assertEquals(25.0, (float) $r['order']->total);
    }

    public function test_an_item_removed_from_the_catalogue_does_not_erase_the_order(): void
    {
        $this->marchand();
        $a = $this->article();
        $this->service()->commander([$a->id => 2], 't-1', ['idempotency_key' => 'k2']);

        $a->delete();

        $this->assertSame(1, ShopOrderItem::count());
        $this->assertSame('Smart Stand A5', ShopOrderItem::firstOrFail()->name);
    }

    /* ------------------------------------------------------------------
       2. Les quantités sont ramenées à ce qu'on sait expédier.
       ------------------------------------------------------------------ */

    public function test_a_quantity_below_the_minimum_is_raised_to_it(): void
    {
        // Un stand se fabrique et s'expédie par lots : accepter « 1 » ferait
        // promettre un envoi qui coûte plus cher que l'objet.
        $a = $this->article(['min_qty' => 10, 'step_qty' => 10]);

        $c = $this->service()->chiffrer([$a->id => 3]);

        $this->assertSame(10, $c['lignes'][0]['qty']);
        $this->assertTrue($c['lignes'][0]['corrigee'], 'L\'écran doit pouvoir DIRE que la quantité a bougé.');
    }

    public function test_a_quantity_between_two_lots_is_rounded_up(): void
    {
        // Vers le HAUT : un marchand qui demande 25 par cartons de 10 en veut
        // trois. Lui en livrer moins qu'il n'a demandé est la seule erreur
        // qu'il remarquera à coup sûr.
        $a = $this->article(['min_qty' => 10, 'step_qty' => 10]);

        $this->assertSame(30, $this->service()->chiffrer([$a->id => 25])['lignes'][0]['qty']);
    }

    public function test_an_exact_lot_is_left_alone(): void
    {
        $a = $this->article(['min_qty' => 10, 'step_qty' => 10]);

        $c = $this->service()->chiffrer([$a->id => 20]);

        $this->assertSame(20, $c['lignes'][0]['qty']);
        $this->assertFalse($c['lignes'][0]['corrigee']);
    }

    public function test_the_corrected_quantity_is_what_gets_ordered(): void
    {
        // Corriger à l'écran mais commander la quantité demandée serait pire
        // que tout : le marchand verrait un chiffre et en recevrait un autre.
        $this->marchand();
        $a = $this->article(['min_qty' => 10, 'step_qty' => 10, 'unit_price' => 10]);

        $r = $this->service()->commander([$a->id => 3], 't-1', ['idempotency_key' => 'k3']);

        $this->assertSame(10, ShopOrderItem::firstOrFail()->qty);
        $this->assertEquals(100.0, (float) $r['order']->total);
    }

    /* ------------------------------------------------------------------
       3. Un double envoi ne commande qu'une fois.
       ------------------------------------------------------------------ */

    public function test_the_same_key_orders_only_once(): void
    {
        // Une connexion haïtienne qui repart, un doigt qui insiste : sans clé,
        // le marchand reçoit deux cartons et une facture double.
        $this->marchand();
        $a = $this->article();

        $un   = $this->service()->commander([$a->id => 2], 't-1', ['idempotency_key' => 'meme']);
        $deux = $this->service()->commander([$a->id => 2], 't-1', ['idempotency_key' => 'meme']);

        $this->assertSame(ShopService::OK, $un['result']);
        $this->assertSame(ShopService::DEJA_FAIT, $deux['result']);
        $this->assertSame($un['order']->id, $deux['order']->id);
        $this->assertSame(1, ShopOrder::count());
    }

    /* ------------------------------------------------------------------
       Le transport, et ce qu'on ne promet pas.
       ------------------------------------------------------------------ */

    public function test_shipping_starts_at_zero_and_is_not_guessed(): void
    {
        // Il dépend d'où est le marchand. Inventer un chiffre à la commande,
        // c'est annoncer un total qu'il faudra démentir.
        $this->marchand();
        $a = $this->article(['unit_price' => 10]);

        $r = $this->service()->commander([$a->id => 2], 't-1', ['idempotency_key' => 'k4']);

        $this->assertEquals(0.0, (float) $r['order']->shipping);
        $this->assertEquals(20.0, (float) $r['order']->total);
        $this->assertSame(OrderStatus::PENDING, $r['order']->status);
    }

    public function test_an_order_starts_as_a_request_not_a_payment(): void
    {
        // Exiger une carte au moment du clic ferait perdre la quasi-totalité
        // des commandes : le marchand paie par MonCash ou à la livraison, une
        // fois le transport connu.
        $this->marchand();
        $a = $this->article();
        $r = $this->service()->commander([$a->id => 1], 't-1', ['idempotency_key' => 'k5']);

        $this->assertNull($r['order']->confirmed_at);
        $this->assertNotNull($r['order']->placed_at);
    }

    /* ------------------------------------------------------------------
       Cloisonnement.
       ------------------------------------------------------------------ */

    public function test_a_merchant_never_sees_another_s_orders(): void
    {
        $this->marchand('t-1');
        $a = $this->article();
        $this->service()->commander([$a->id => 1], 't-1', ['idempotency_key' => 'a-moi']);
        $this->service()->commander([$a->id => 1], 't-2', ['idempotency_key' => 'au-voisin']);

        $this->get(route('tagtoa.shop.orders'))->assertOk()
            ->assertSee('A-MOI')->assertDontSee('AU-VOISIN');

        $this->assertSame(1, ShopOrder::count(), 'La portée automatique doit filtrer.');
        $this->assertSame(2, ShopOrder::allTenants()->count());
    }

    /* ------------------------------------------------------------------
       Les écrans du marchand.
       ------------------------------------------------------------------ */

    public function test_the_shop_opens_and_shows_what_is_for_sale(): void
    {
        $this->marchand();
        $this->article(['name' => 'Smart Stand A5']);
        $this->article(['name' => 'Carte NFC', 'is_active' => false]);

        $this->get(route('tagtoa.shop.index'))->assertOk()
            ->assertSee('Smart Stand A5')
            ->assertDontSee('Carte NFC');
    }

    public function test_the_cart_survives_from_one_screen_to_the_next(): void
    {
        $this->marchand();
        $a = $this->article(['min_qty' => 10, 'step_qty' => 10]);

        $this->post(route('tagtoa.shop.cart'), ['add' => $a->id])->assertRedirect();

        // La quantité de départ respecte déjà le lot : afficher « 1 » sur un
        // article qui se vend par dix ferait croire qu'on peut en prendre un.
        $this->assertSame([$a->id => 10], session('tagtoa_shop_cart'));

        $this->get(route('tagtoa.shop.checkout'))->assertOk()->assertSee('Smart Stand A5');
    }

    public function test_an_empty_cart_cannot_reach_the_checkout(): void
    {
        $this->marchand();

        $this->get(route('tagtoa.shop.checkout'))->assertRedirect(route('tagtoa.shop.index'));
    }

    public function test_the_whole_order_goes_through_the_form(): void
    {
        $this->marchand();
        $a = $this->article(['unit_price' => 12.50]);
        $this->post(route('tagtoa.shop.cart'), ['add' => $a->id]);

        $this->post(route('tagtoa.shop.store'), [
            'contact_name'    => 'Rose Chérie',
            'contact_phone'   => '+509 3123 4567',
            'address'         => '12, rue Pavée',
            'city'            => 'Port-au-Prince',
            'idempotency_key' => 'form-1',
        ])->assertRedirect(route('tagtoa.shop.orders'))->assertSessionHas('success');

        $o = ShopOrder::firstOrFail();
        $this->assertSame('Rose Chérie', $o->contact_name);
        $this->assertEquals(12.50, (float) $o->total);

        // Le panier se vide SEULEMENT une fois la commande en base.
        $this->assertEmpty(session('tagtoa_shop_cart', []));
    }

    public function test_an_order_without_a_delivery_address_is_refused(): void
    {
        // Un carton sans adresse est un carton qui ne part pas.
        $this->marchand();
        $a = $this->article();
        $this->post(route('tagtoa.shop.cart'), ['add' => $a->id]);

        $this->post(route('tagtoa.shop.store'), ['idempotency_key' => 'x'])
            ->assertSessionHasErrors(['contact_name', 'contact_phone', 'address']);

        $this->assertSame(0, ShopOrder::count());
    }

    /* ------------------------------------------------------------------
       Le fondateur.
       ------------------------------------------------------------------ */

    private function fondateur(): void
    {
        $this->be(new GenericUser(['id' => 9, 'tenant_id' => 't-tagtoa', 'name' => 'Roosevelt']));
    }

    public function test_the_founder_sees_every_merchant_s_orders(): void
    {
        // C'est la raison d'être de l'écran : c'est TAGTOA qui expédie.
        $a = $this->article();
        $this->service()->commander([$a->id => 1], 't-1', ['idempotency_key' => 'chez-un']);
        $this->service()->commander([$a->id => 1], 't-2', ['idempotency_key' => 'chez-deux']);

        $this->fondateur();

        $this->get(route('tagtoa.superadmin.shop'))->assertOk()
            ->assertSee('CHEZ-UN')->assertSee('CHEZ-DEU');
    }

    public function test_the_founder_sets_the_shipping_and_the_total_follows(): void
    {
        $a = $this->article(['unit_price' => 10]);
        $r = $this->service()->commander([$a->id => 4], 't-1', ['idempotency_key' => 'k9']);
        $this->fondateur();

        $this->put(route('tagtoa.superadmin.shop.order.update', $r['order']->id), [
            'status' => OrderStatus::CONFIRMED, 'shipping' => 15, 'reply' => 'Départ lundi',
        ])->assertRedirect();

        $o = ShopOrder::allTenants()->findOrFail($r['order']->id);
        $this->assertEquals(15.0, (float) $o->shipping);
        // Recalculé, jamais repris du formulaire.
        $this->assertEquals(55.0, (float) $o->total);
        $this->assertSame('Départ lundi', $o->reply);
        $this->assertNotNull($o->confirmed_at);
    }

    public function test_a_stage_date_is_never_rewritten(): void
    {
        // La réécrire à chaque enregistrement effacerait le moment où la chose
        // s'est réellement passée — la seule information qui permette de dire,
        // plus tard, si le délai promis a été tenu.
        $a = $this->article();
        $r = $this->service()->commander([$a->id => 1], 't-1', ['idempotency_key' => 'k10']);
        $this->fondateur();

        $this->put(route('tagtoa.superadmin.shop.order.update', $r['order']->id),
            ['status' => OrderStatus::CONFIRMED]);
        $premiere = ShopOrder::allTenants()->find($r['order']->id)->confirmed_at;

        $this->travel(2)->days();
        $this->put(route('tagtoa.superadmin.shop.order.update', $r['order']->id),
            ['status' => OrderStatus::CONFIRMED, 'reply' => 'relance']);

        $this->assertEquals($premiere,
            ShopOrder::allTenants()->find($r['order']->id)->confirmed_at);
    }

    public function test_an_invented_status_is_refused(): void
    {
        $a = $this->article();
        $r = $this->service()->commander([$a->id => 1], 't-1', ['idempotency_key' => 'k11']);
        $this->fondateur();

        $this->put(route('tagtoa.superadmin.shop.order.update', $r['order']->id),
            ['status' => 'parti-peut-etre'])->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::PENDING,
            ShopOrder::allTenants()->find($r['order']->id)->status);
    }

    public function test_the_founder_can_publish_and_edit_the_catalogue(): void
    {
        $this->fondateur();

        $this->post(route('tagtoa.superadmin.shop.items'), [
            'sku' => 'STAND-A5', 'name' => 'Smart Stand A5', 'unit_price' => 12.5,
            'min_qty' => 10, 'step_qty' => 10, 'lead_time_days' => 21,
        ])->assertRedirect()->assertSessionHas('success');

        $it = ShopItem::firstOrFail();
        $this->assertSame(10, $it->min_qty);

        $this->put(route('tagtoa.superadmin.shop.item.update', $it->id), [
            'sku' => 'STAND-A5', 'name' => 'Smart Stand A5', 'unit_price' => 14, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertEquals(14.0, (float) $it->refresh()->unit_price);
    }

    public function test_the_founder_s_screens_really_sit_behind_the_role(): void
    {
        // CE TEST EXISTE PARCE QUE LE HARNAIS MENT SUR CE POINT.
        //
        // `role` est remplacé par un passe-plat dans les tests (le rôle vit
        // dans le cœur Biztap, absent d'ici) : les tests ci-dessus passeraient
        // donc même si la boutique du fondateur était grande ouverte. On
        // vérifie la DÉCLARATION, faute de pouvoir vérifier l'exécution.
        //
        // Sans cela, n'importe quel marchand connecté lirait les commandes,
        // les adresses et les téléphones de TOUS les autres.
        $routes = (string) file_get_contents(__DIR__.'/../../routes/web.php');

        $bloc = strstr($routes, "role:super_admin");
        $this->assertNotFalse($bloc, 'Le groupe super-admin a disparu.');

        foreach (['superadmin.shop', 'superadmin.shop.items',
                  'superadmin.shop.item.update', 'superadmin.shop.order.update'] as $nom) {
            $this->assertStringContainsString($nom, $bloc,
                "La route « $nom » n'est pas dans le groupe role:super_admin.");
        }
    }

    public function test_two_items_cannot_share_a_reference(): void
    {
        // La référence part sur le bon de commande et sur le carton : deux
        // articles qui la partagent, c'est un envoi qu'on ne sait plus lire.
        $this->fondateur();
        $this->article(['sku' => 'STAND-A5']);

        $this->post(route('tagtoa.superadmin.shop.items'),
            ['sku' => 'STAND-A5', 'name' => 'Doublon', 'unit_price' => 5])
            ->assertSessionHasErrors('sku');
    }
}
