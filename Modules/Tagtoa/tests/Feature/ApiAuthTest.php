<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA API — middleware d'authentification par clé. C'est la frontière de
| sécurité de l'API publique : chaque cas de rejet est vérifié explicitement.
|--------------------------------------------------------------------------
*/

use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Http\Middleware\AuthenticateApiKey;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Support\Api\ApiToken;
use Modules\Tagtoa\Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Fabrique une clé et renvoie [modèle, jeton en clair]. */
    private function makeKey(array $attrs = []): array
    {
        $g = ApiToken::generate();
        $key = ApiKey::create(array_merge([
            'tenant_id'  => 't1',
            'name'       => 'Intégration test',
            'key_prefix' => $g['prefix'],
            'key_hash'   => $g['hash'],
        ], $attrs));

        return [$key, $g['token']];
    }

    /** Exécute le middleware avec l'en-tête donné et renvoie [statut, requête]. */
    private function callMiddleware(?string $authorization): array
    {
        $request = Request::create('/api/v1/tagtoa/ping', 'GET');
        if ($authorization !== null) {
            $request->headers->set('Authorization', $authorization);
        }

        $reached = false;
        $response = (new AuthenticateApiKey)->handle($request, function ($r) use (&$reached) {
            $reached = true;

            return response()->json(['ok' => true]);
        });

        return [$response->getStatusCode(), $request, $reached];
    }

    public function test_a_valid_key_is_accepted_and_exposes_its_tenant(): void
    {
        [$key, $token] = $this->makeKey();

        [$status, $request, $reached] = $this->callMiddleware('Bearer '.$token);

        $this->assertSame(200, $status);
        $this->assertTrue($reached);
        $this->assertSame('t1', $request->attributes->get('tagtoa_tenant_id'));
        $this->assertSame($key->id, $request->attributes->get('tagtoa_api_key')->id);
    }

    public function test_a_valid_key_gets_its_last_use_stamped(): void
    {
        [$key, $token] = $this->makeKey();
        $this->assertNull($key->last_used_at);

        $this->callMiddleware('Bearer '.$token);

        $this->assertNotNull($key->fresh()->last_used_at);
    }

    public function test_a_request_without_any_key_is_rejected(): void
    {
        [$status, , $reached] = $this->callMiddleware(null);

        $this->assertSame(401, $status);
        $this->assertFalse($reached);
    }

    public function test_a_revoked_key_is_rejected(): void
    {
        [$key, $token] = $this->makeKey(['revoked_at' => now()]);

        [$status, , $reached] = $this->callMiddleware('Bearer '.$token);

        $this->assertSame(401, $status);
        $this->assertFalse($reached);
    }

    public function test_a_right_prefix_with_a_wrong_secret_is_rejected(): void
    {
        // Cas critique : connaître le préfixe (qui est affiché au marchand) ne
        // doit jamais suffire à s'authentifier.
        [$key] = $this->makeKey();

        [$status, , $reached] = $this->callMiddleware('Bearer '.$key->key_prefix.'_faux_secret_completement_invente');

        $this->assertSame(401, $status);
        $this->assertFalse($reached);
    }

    public function test_unknown_and_malformed_keys_are_rejected(): void
    {
        foreach (['Bearer tag_live_zzzzzzzz_inexistant', 'Bearer nimportequoi', 'Bearer ', 'Basic abc'] as $header) {
            [$status, , $reached] = $this->callMiddleware($header);
            $this->assertSame(401, $status, 'devrait être refusé : '.$header);
            $this->assertFalse($reached);
        }
    }

    public function test_the_raw_token_is_also_accepted_without_the_bearer_prefix(): void
    {
        [, $token] = $this->makeKey();

        [$status] = $this->callMiddleware($token);

        $this->assertSame(200, $status);
    }
}
