<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — Stripe : la clé doit correspondre à l'environnement annoncé
|--------------------------------------------------------------------------
| Stripe n'a qu'une seule URL d'API : test et production ne se distinguent
| QUE par le préfixe de la clé secrète (sk_test_… / sk_live_…). Sans ce
| garde-fou, une clé sk_live_ collée en pensant être en mode test facturerait
| un vrai client pendant un essai — et l'inverse ne facturerait jamais
| personne alors que le marchand croit encaisser en production.
|
| On lit directement le secret retenu par le driver (réflexion) plutôt que
| de passer par Http::fake() : createPayment() renvoie déjà null sans appel
| réseau dès que le secret est neutralisé, donc l'assertion la plus directe
| sur LE garde-fou lui-même est l'état interne qu'il produit, pas une requête
| HTTP qui de toute façon n'est jamais envoyée dans le cas correct.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\GatewayCredential;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Gateways\StripeDriver;
use Modules\Tagtoa\Tests\TestCase;

class StripeDriverModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
    }

    protected function tearDown(): void
    {
        GatewayManager::flush();
        parent::tearDown();
    }

    private function transaction(): PayTransaction
    {
        return PayTransaction::create([
            'tenant_id' => 't-1', 'gateway' => 'stripe', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'pay_page', 'order_id' => 1, 'amount' => 25, 'currency' => 'USD',
            'status' => PayTransaction::STATUS_PENDING,
        ]);
    }

    private function stocker(string $mode, string $secret): void
    {
        GatewayCredential::create([
            'driver' => 'stripe',
            'values' => ['mode' => $mode, 'credentials' => ['key' => 'pk_x', 'secret' => $secret]],
        ]);
        GatewayManager::flush();
    }

    /** Le secret réellement retenu par le driver, sans exposer d'API publique juste pour un test. */
    private function secretRetenu(StripeDriver $driver): ?string
    {
        $prop = new \ReflectionProperty($driver, 'secret');
        $prop->setAccessible(true);

        return $prop->getValue($driver);
    }

    public function test_a_live_key_declared_as_sandbox_disables_the_driver(): void
    {
        $this->stocker('sandbox', 'sk_live_reelle');

        $driver = new StripeDriver();

        $this->assertNull($this->secretRetenu($driver));
        $this->assertNull($driver->createPayment($this->transaction()));
    }

    public function test_a_test_key_declared_as_live_disables_the_driver(): void
    {
        $this->stocker('live', 'sk_test_factice');

        $driver = new StripeDriver();

        $this->assertNull($this->secretRetenu($driver));
        $this->assertNull($driver->createPayment($this->transaction()));
    }

    public function test_a_matching_key_and_mode_keeps_the_secret_active(): void
    {
        $this->stocker('sandbox', 'sk_test_valide');

        $driver = new StripeDriver();

        $this->assertSame('sk_test_valide', $this->secretRetenu($driver));
    }
}
