<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA TENANT — le back-office ne s'ouvre pas sans commerce (CS-11b)
|--------------------------------------------------------------------------
| Un compte connecté SANS tenant_id obtient Tenant::id() === null, donc une
| portée automatique vide : il voyait les données de tous les commerces.
| Production (CS-11a) : 0 compte sur 9 concerné — on ferme avant qu'il existe.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Tagtoa\App\Http\Middleware\RequireTenant;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class RequireTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::flush();
        Route::middleware(['web', RequireTenant::class])->get('/__probe', fn () => response()->json([
            'menus' => Menu::count(),
        ]));
    }

    protected function tearDown(): void
    {
        Tenant::flush();
        parent::tearDown();
    }

    private function seedTwoBusinesses(): void
    {
        Menu::create(['tenant_id' => 'a', 'name' => 'A', 'alias' => 'a', 'currency' => 'HTG', 'is_active' => true]);
        Menu::create(['tenant_id' => 'b', 'name' => 'B', 'alias' => 'b', 'currency' => 'HTG', 'is_active' => true]);
    }

    public function test_authenticated_user_without_tenant_gets_403_and_sees_nothing(): void
    {
        $this->seedTwoBusinesses();
        $this->be(new GenericUser(['id' => 9, 'tenant_id' => null]));

        $this->get('/__probe')->assertForbidden()->assertDontSee('menus');
        $this->getJson('/__probe')->assertForbidden()->assertJsonMissing(['menus' => 2]);
    }

    public function test_empty_string_tenant_is_refused_too(): void
    {
        $this->be(new GenericUser(['id' => 9, 'tenant_id' => '']));
        $this->get('/__probe')->assertForbidden();
    }

    public function test_user_with_a_tenant_sees_only_its_own_rows(): void
    {
        $this->seedTwoBusinesses();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 'a']));

        $this->get('/__probe')->assertOk()->assertJson(['menus' => 1]);
    }

    public function test_anonymous_request_is_refused_by_this_guard_not_opened(): void
    {
        $this->get('/__probe')->assertForbidden();
    }

    /** La garde est bien posée sur le groupe marchand — et PAS sur l'espace super-admin ni le public. */
    public function test_guard_is_on_the_merchant_dashboard_only(): void
    {
        $guarded = $open = [];
        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            if ($uri === '__probe') {
                continue;
            }
            $has = in_array(RequireTenant::class, $r->gatherMiddleware(), true);
            if (str_starts_with($uri, 'tagtoa/admin')) {
                $this->assertFalse($has, "$uri (super-admin cross-commerce) ne doit pas être bloqué.");
            }
            $has ? $guarded[] = $uri : $open[] = $uri;
        }

        $this->assertContains('tagtoa/home', $guarded);
        $this->assertNotEmpty(array_filter($guarded, fn ($u) => str_starts_with($u, 'tagtoa/menu')), 'Le tableau de bord Menu est gardé.');
        $this->assertNotEmpty(array_filter($guarded, fn ($u) => str_starts_with($u, 'tagtoa/pos')), 'Le POS marchand est gardé.');
        foreach (['menu/{alias}', 'pay/{alias}', 'pay/result'] as $publicUri) {
            $this->assertNotContains($publicUri, $guarded, "$publicUri est public : jamais derrière RequireTenant.");
        }
        foreach ($guarded as $uri) {
            $this->assertStringStartsWith('tagtoa', $uri);
        }
    }
}
