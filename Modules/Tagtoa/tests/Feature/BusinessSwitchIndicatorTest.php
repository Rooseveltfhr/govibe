<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — sur QUEL commerce suis-je en train de travailler ?
|--------------------------------------------------------------------------
| Signalé après l'audit : Tenant::switchTo() existe, « Mes commerces »
| permet déjà de changer de commerce, mais RIEN dans le tableau de bord ne
| dit sur LEQUEL on travaille en ce moment — un marchand qui gère un
| restaurant ET une quincaillerie pouvait ajouter un produit au mauvais
| commerce sans même s'en rendre compte. Ce test fige l'indicateur : visible
| SEULEMENT quand il y a un vrai choix à faire (2+ commerces actifs).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class BusinessSwitchIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(string $accountId): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $accountId]));
        Tenant::flush();
    }

    public function test_an_account_with_a_single_business_sees_no_switcher(): void
    {
        $this->connecte('compte-1');
        app(BusinessService::class)->create('compte-1', [
            'name' => 'Lakay Grill', 'type' => 'restaurant',
            'sells_products' => true, 'currency' => 'HTG',
        ]);

        $html = $this->get('/tagtoa/home')->assertOk()->getContent();

        // PAS 'tg-biz' tout court : ce nom de classe reste présent dans la
        // feuille de style (co-localisée, toujours servie — même convention
        // que partials/lang.blade.php). Seul l'élément <details> lui-même ne
        // doit pas s'afficher quand il n'y a rien à choisir.
        $this->assertStringNotContainsString('<details class="tg-biz">', $html);
    }

    public function test_an_account_with_two_businesses_sees_the_current_one_and_the_other(): void
    {
        $this->connecte('compte-1');
        $svc = app(BusinessService::class);
        $svc->create('compte-1', [
            'name' => 'Lakay Grill', 'type' => 'restaurant',
            'sells_products' => true, 'currency' => 'HTG',
        ]);
        $svc->create('compte-1', [
            'name' => 'Boutique Kado', 'type' => 'boutique',
            'sells_products' => true, 'currency' => 'HTG',
        ]);

        // create() bascule déjà sur le dernier créé (Boutique Kado) : on revient
        // délibérément sur le premier pour vérifier que c'est bien LUI qui
        // s'affiche comme courant, pas « le plus récent » par coïncidence.
        $premier = Business::where('account_id', 'compte-1')->where('name', 'Lakay Grill')->firstOrFail();
        Tenant::switchTo($premier->id);

        $html = $this->get('/tagtoa/home')->assertOk()->getContent();

        $this->assertStringContainsString('<details class="tg-biz">', $html);
        $this->assertStringContainsString('Lakay Grill', $html);
        $this->assertStringContainsString('Boutique Kado', $html);
    }

    public function test_the_switcher_only_lists_the_accounts_own_businesses(): void
    {
        $this->connecte('compte-1');
        $svc = app(BusinessService::class);
        $svc->create('compte-1', ['name' => 'Lakay Grill', 'type' => 'restaurant', 'sells_products' => true, 'currency' => 'HTG']);
        $svc->create('compte-1', ['name' => 'Boutique Kado', 'type' => 'boutique', 'sells_products' => true, 'currency' => 'HTG']);
        $svc->create('compte-voisin', ['name' => 'Commerce du voisin', 'type' => 'other', 'sells_products' => true, 'currency' => 'HTG']);

        $html = $this->get('/tagtoa/home')->assertOk()->getContent();

        $this->assertStringNotContainsString('Commerce du voisin', $html);
    }

    public function test_a_brand_new_account_with_no_business_declared_sees_no_switcher(): void
    {
        $this->connecte('compte-1');

        $html = $this->get('/tagtoa/home')->assertOk()->getContent();

        $this->assertStringNotContainsString('<details class="tg-biz">', $html);
    }

    public function test_the_switcher_links_to_the_full_businesses_page(): void
    {
        $this->connecte('compte-1');
        $svc = app(BusinessService::class);
        $svc->create('compte-1', ['name' => 'Lakay Grill', 'type' => 'restaurant', 'sells_products' => true, 'currency' => 'HTG']);
        $svc->create('compte-1', ['name' => 'Boutique Kado', 'type' => 'boutique', 'sells_products' => true, 'currency' => 'HTG']);

        $this->get('/tagtoa/home')->assertOk()
            ->assertSee(route('tagtoa.business.index'), false);
    }
}
