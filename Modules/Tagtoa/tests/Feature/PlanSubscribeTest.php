<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA BILLING — un forfait « sur devis » ne se prend pas en libre-service
|--------------------------------------------------------------------------
| Enterprise et Franchise ont `price => null` (« sur devis »). Le contrôleur
| lisait `(float) ($plans[$plan]['price'] ?? 0)` : null devenait 0, donc le
| forfait passait pour GRATUIT et s'activait aussitôt, sans date de fin.
|
| Et ce n'était pas un POST fabriqué à la main : la page des forfaits affichait
| « Choisir ce forfait » sur chaque forfait, « Sur devis » compris. Un
| marchand qui cliquait sur Enterprise obtenait tout illimité — dont `cards`,
| l'émission de cartes NFC, réservée aux forfaits payants supérieurs — pour
| toujours et pour rien.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Billing\PlanDefinition;
use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Services\Billing\PlanService;
use Modules\Tagtoa\Tests\TestCase;

class PlanSubscribeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlanService::flush();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1']));
    }

    protected function tearDown(): void
    {
        PlanService::flush();
        parent::tearDown();
    }

    /** @dataProvider quoteOnlyPlans */
    public function test_a_quote_only_plan_cannot_be_self_granted(string $plan): void
    {
        $this->post(route('tagtoa.plan.subscribe'), ['plan' => $plan])
            ->assertSessionHas('error');

        $this->assertSame(0, Subscription::count(), "« $plan » ne doit jamais s'activer sans devis.");
    }

    public static function quoteOnlyPlans(): array
    {
        return ['enterprise' => ['enterprise'], 'franchise' => ['franchise']];
    }

    public function test_the_plan_page_offers_no_subscribe_button_on_a_quote_only_plan(): void
    {
        $html = $this->get(route('tagtoa.plan.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="plan" value="enterprise"', $html);
        $this->assertStringNotContainsString('name="plan" value="franchise"', $html);
        // Les forfaits à prix connu gardent leur bouton.
        $this->assertStringContainsString('name="plan" value="pro"', $html);
    }

    public function test_a_deactivated_plan_cannot_be_subscribed(): void
    {
        PlanDefinition::create(['plan_key' => 'free', 'price' => 0, 'is_active' => false]);
        PlanService::flush();

        $this->post(route('tagtoa.plan.subscribe'), ['plan' => 'free'])
            ->assertSessionHas('error');

        $this->assertSame(0, Subscription::count());
    }

    /** Garde de non-régression : le libre-service du forfait gratuit reste intact. */
    public function test_the_free_plan_is_still_self_service(): void
    {
        $this->post(route('tagtoa.plan.subscribe'), ['plan' => 'free'])
            ->assertSessionHas('success');

        $this->assertSame('free', Subscription::first()->plan);
    }

    /** Garde de non-régression : un forfait payant sans passerelle n'est jamais offert. */
    public function test_a_paid_plan_is_not_granted_without_a_payment(): void
    {
        $this->post(route('tagtoa.plan.subscribe'), ['plan' => 'pro'])
            ->assertSessionHas('error');

        $this->assertSame(0, Subscription::count());
    }
}
