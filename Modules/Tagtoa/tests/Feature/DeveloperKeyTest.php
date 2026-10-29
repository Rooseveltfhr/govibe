<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — espace développeur : création d'une clé API.
|--------------------------------------------------------------------------
| Les deux secrets d'une intégration naissent ici. Le jeton d'appel était
| bien montré une fois ; le secret de signature des webhooks, lui, ne l'était
| JAMAIS — alors que la documentation affichée juste en dessous demande à
| l'intégrateur de vérifier l'en-tête X-Tagtoa-Signature avec. La vérification
| HMAC était donc irréalisable par n'importe quel destinataire.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\Tests\TestCase;

class DeveloperKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // L'espace développeur est derrière l'authentification marchande :
        // sans session ouverte, Tenant::id() n'a aucun commerce à rattacher.
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));
    }

    public function test_creating_a_key_reveals_both_the_token_and_the_webhook_secret_once(): void
    {
        $response = $this->post(route('tagtoa.developer.store'), ['name' => 'Mon site']);

        $key = ApiKey::first();
        $this->assertNotNull($key);

        $response->assertSessionHas('new_api_token');
        $response->assertSessionHas('new_webhook_secret', $key->webhook_secret);
    }

    public function test_the_webhook_secret_is_shown_once_then_never_again(): void
    {
        // Même politique que le jeton d'appel : lisible sur l'écran qui suit la
        // création, puis plus jamais — sinon un secret de signature traînerait
        // en clair sur une page que le marchand laisse ouverte.
        $this->post(route('tagtoa.developer.store'), ['name' => 'Mon site']);
        $secret = ApiKey::first()->webhook_secret;

        $this->get(route('tagtoa.developer.index'))->assertOk()->assertSee($secret);
        $this->get(route('tagtoa.developer.index'))->assertOk()->assertDontSee($secret);
    }
}
