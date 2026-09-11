<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Un compte TAGTOA peut tenir PLUSIEURS commerces.
 *
 * Le commerce est l'unité du système : Tenant::id() renvoie son identifiant,
 * donc tout ce qui porte un tenant_id lui appartient. Le premier commerce d'un
 * compte déjà installé reprend son ancien identifiant — c'est ce qui permet de
 * ne réécrire aucune ligne en base.
 */
class BusinessTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(string $accountId): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $accountId]));
        Tenant::flush();
    }

    private function service(): BusinessService
    {
        return app(BusinessService::class);
    }

    public function test_the_first_business_keeps_the_accounts_old_identifier(): void
    {
        // Le point capital de la reprise : un marchand installé depuis des mois
        // a des menus, des ventes et des liens rattachés à « compte-1 ». Si son
        // premier commerce recevait un nouvel identifiant, tout disparaîtrait
        // de sa vue.
        $this->connecte('compte-1');

        $boulangerie = $this->service()->create('compte-1', ['name' => 'Boulangerie Delmas']);

        $this->assertSame('compte-1', $boulangerie->id);
    }

    public function test_data_created_before_the_business_existed_stays_visible(): void
    {
        // Données « d'avant », rattachées au compte.
        Menu::create(['tenant_id' => 'compte-1', 'name' => 'Ancien menu', 'alias' => 'ancien', 'currency' => 'HTG']);

        $this->connecte('compte-1');
        $this->service()->create('compte-1', ['name' => 'Boulangerie Delmas']);

        $this->assertSame('compte-1', Tenant::id());
        $this->assertSame(['Ancien menu'], Menu::pluck('name')->all());
    }

    public function test_a_second_business_gets_its_own_identifier_and_its_own_data(): void
    {
        $this->connecte('compte-1');

        $boulangerie = $this->service()->create('compte-1', ['name' => 'Boulangerie']);
        Menu::create(['name' => 'Pains', 'alias' => 'pains', 'currency' => 'HTG']);

        $bar = $this->service()->create('compte-1', ['name' => 'Bar Lakay']);
        Menu::create(['name' => 'Boissons', 'alias' => 'boissons', 'currency' => 'HTG']);

        $this->assertNotSame($boulangerie->id, $bar->id);

        // On travaille sur le bar : on ne voit que le bar.
        $this->assertSame($bar->id, Tenant::id());
        $this->assertSame(['Boissons'], Menu::pluck('name')->all());

        // Et en revenant à la boulangerie, on retrouve la boulangerie.
        Tenant::switchTo($boulangerie->id);
        $this->assertSame(['Pains'], Menu::pluck('name')->all());
    }

    public function test_a_new_record_is_attached_to_the_business_being_worked_on(): void
    {
        $this->connecte('compte-1');
        $this->service()->create('compte-1', ['name' => 'Boulangerie']);
        $bar = $this->service()->create('compte-1', ['name' => 'Bar']);

        $menu = Menu::create(['name' => 'Carte', 'alias' => 'carte', 'currency' => 'HTG']);

        $this->assertSame($bar->id, $menu->tenant_id);
    }

    public function test_no_one_switches_to_a_business_that_is_not_his(): void
    {
        $this->connecte('compte-1');
        $chezMoi = $this->service()->create('compte-1', ['name' => 'Ma boulangerie']);

        $chezAutrui = Business::create([
            'id' => 'autre-commerce', 'account_id' => 'compte-2', 'name' => 'Chez le voisin',
        ]);

        $this->assertFalse(Tenant::switchTo($chezAutrui->id), 'La bascule doit être refusée.');
        $this->assertSame($chezMoi->id, Tenant::id(), 'Et le commerce courant ne doit pas bouger.');
    }

    public function test_a_business_that_gets_closed_stops_being_the_current_one(): void
    {
        // Commerce cédé ou fermé pendant que la session est encore ouverte :
        // on ne suit pas la session les yeux fermés.
        $this->connecte('compte-1');
        $boulangerie = $this->service()->create('compte-1', ['name' => 'Boulangerie']);
        $bar = $this->service()->create('compte-1', ['name' => 'Bar']);

        $bar->update(['is_active' => false]);
        Tenant::flush();

        $this->assertSame($boulangerie->id, Tenant::id());
    }

    public function test_a_merchant_who_has_not_declared_his_business_keeps_working(): void
    {
        // Personne ne doit se retrouver bloqué parce qu'il n'a pas encore rempli
        // le formulaire : on retombe sur le compte, donc sur ses données.
        Menu::create(['tenant_id' => 'compte-9', 'name' => 'Son menu', 'alias' => 'sien', 'currency' => 'HTG']);

        $this->connecte('compte-9');

        $this->assertSame('compte-9', Tenant::id());
        $this->assertSame(['Son menu'], Menu::pluck('name')->all());
    }

    public function test_a_public_visitor_belongs_to_no_business(): void
    {
        $this->assertNull(Tenant::id(), 'Sans compte connecté, aucun commerce courant.');
    }

    public function test_a_business_that_sells_nothing_is_never_recorded(): void
    {
        $this->connecte('compte-1');

        $b = $this->service()->create('compte-1', [
            'name' => 'Commerce', 'sells_products' => false, 'sells_services' => false,
        ]);

        $this->assertTrue($b->sells_products, 'Ni produit ni service ne veut rien dire.');
    }

    public function test_a_business_can_sell_both_products_and_services(): void
    {
        // Un hôtel vend des nuits (service) et des boissons (produit).
        $this->connecte('compte-1');

        $hotel = $this->service()->create('compte-1', [
            'name' => 'Hôtel Cap', 'type' => 'hotel',
            'sells_products' => true, 'sells_services' => true,
        ]);

        $this->assertSame('Produits et services', $hotel->sells_label);
        $this->assertSame('Hôtel', $hotel->type_label);
    }

    public function test_categories_are_accepted_as_a_list_or_as_free_text(): void
    {
        $this->connecte('compte-1');

        $a = $this->service()->create('compte-1', ['name' => 'A', 'categories' => ['Pains', 'Gâteaux']]);
        $b = $this->service()->create('compte-1', ['name' => 'B', 'categories' => ' Pains , Gâteaux ,, ']);

        $this->assertSame(['Pains', 'Gâteaux'], $a->categories);
        $this->assertSame(['Pains', 'Gâteaux'], $b->categories, 'Les entrées vides doivent tomber.');
    }

    public function test_the_merchant_may_use_a_currency_we_do_not_list(): void
    {
        $this->connecte('compte-1');

        $b = $this->service()->create('compte-1', ['name' => 'Diaspora', 'currency' => ' brl ']);

        $this->assertSame('BRL', $b->currency);
        $this->assertSame('BRL', Tenant::currency());
    }

    public function test_the_current_business_is_resolved_once_per_request(): void
    {
        // Tenant::id() est appelé par la portée automatique à CHAQUE requête
        // Eloquent : sans cache, afficher trente produits interrogerait trente
        // fois la table des commerces.
        $this->connecte('compte-1');
        $this->service()->create('compte-1', ['name' => 'Boulangerie']);

        Tenant::flush();

        \Illuminate\Support\Facades\DB::enableQueryLog();
        for ($i = 0; $i < 5; $i++) {
            Tenant::id();
        }
        $requetes = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Une seule résolution pour cinq appels : c'est le cache qui travaille.
        $this->assertCount(1, $requetes,
            'Cinq appels doivent coûter une seule lecture, pas cinq.');
    }

    public function test_flushing_the_cache_makes_the_next_call_look_again(): void
    {
        // Le cache ne doit pas survivre à un changement de commerce, sinon la
        // bascule n'aurait aucun effet jusqu'à la requête suivante.
        $this->connecte('compte-1');
        $boulangerie = $this->service()->create('compte-1', ['name' => 'Boulangerie']);
        $bar = $this->service()->create('compte-1', ['name' => 'Bar']);

        $this->assertSame($bar->id, Tenant::id());

        Tenant::switchTo($boulangerie->id);

        $this->assertSame($boulangerie->id, Tenant::id(), 'La bascule doit être immédiate.');
    }

    public function test_logging_in_after_a_first_lookup_still_isolates(): void
    {
        // Régression. Le cache du commerce courant était global : une lecture
        // faite AVANT la connexion y figeait « aucun commerce », et l'isolation
        // disparaissait pour tout le reste de la requête. Il est maintenant
        // indexé par compte, donc se connecter invalide la valeur tout seul.
        Menu::create(['tenant_id' => 'compte-1', 'name' => 'Chez moi', 'alias' => 'moi', 'currency' => 'HTG']);
        Menu::create(['tenant_id' => 'compte-2', 'name' => 'Chez le voisin', 'alias' => 'voisin', 'currency' => 'HTG']);

        // Lecture publique : aucun commerce courant, aucune portée. Normal.
        $this->assertNull(Tenant::id());
        $this->assertSame(2, Menu::count());

        // On se connecte SANS vider quoi que ce soit à la main.
        $this->be(new \Illuminate\Auth\GenericUser(['id' => 1, 'tenant_id' => 'compte-1']));

        $this->assertSame('compte-1', Tenant::id());
        $this->assertSame(['Chez moi'], Menu::pluck('name')->all());
    }

    public function test_two_accounts_in_the_same_request_never_see_each_other(): void
    {
        Menu::create(['tenant_id' => 'compte-1', 'name' => 'A', 'alias' => 'a', 'currency' => 'HTG']);
        Menu::create(['tenant_id' => 'compte-2', 'name' => 'B', 'alias' => 'b', 'currency' => 'HTG']);

        $this->be(new \Illuminate\Auth\GenericUser(['id' => 1, 'tenant_id' => 'compte-1']));
        $this->assertSame(['A'], Menu::pluck('name')->all());

        $this->be(new \Illuminate\Auth\GenericUser(['id' => 2, 'tenant_id' => 'compte-2']));
        $this->assertSame(['B'], Menu::pluck('name')->all());
    }
}
