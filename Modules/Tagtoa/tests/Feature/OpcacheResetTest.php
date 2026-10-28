<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — vidage OPcache post-déploiement
|--------------------------------------------------------------------------
| Diagnostic de session : un correctif mergé + déployé avec succès (CI
| verte, migrations exécutées) restait invisible sur tagtoa.com. Reproduit
| localement en rendant la page via ce même harnais de test : le HTML/CSS
| est correct dès qu'on le rend frais — ce que les workers PHP-FPM déjà
| démarrés ne font PAS tant qu'OPcache n'est pas vidé. Cette route est le
| mécanisme de vidage, appelée par deploy/remote-deploy.sh après chaque
| déploiement.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class OpcacheResetTest extends TestCase
{
    use RefreshDatabase;


    public function test_the_route_refuses_when_no_token_is_configured(): void
    {
        config(['tagtoa.ops.opcache_reset_token' => null]);

        $this->get('/tagtoa-ops/opcache-reset/anything')->assertNotFound();
    }

    public function test_the_route_refuses_a_wrong_token(): void
    {
        config(['tagtoa.ops.opcache_reset_token' => 'le-bon-jeton']);

        $this->get('/tagtoa-ops/opcache-reset/un-mauvais-jeton')->assertNotFound();
    }

    public function test_the_route_accepts_the_configured_token(): void
    {
        config(['tagtoa.ops.opcache_reset_token' => 'le-bon-jeton']);

        $this->get('/tagtoa-ops/opcache-reset/le-bon-jeton')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
