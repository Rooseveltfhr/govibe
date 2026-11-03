<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA ABONNEMENTS (R0-a) — l'expiration, sans jamais punir un abonné qui paie
|--------------------------------------------------------------------------
| Avant : `Subscription::planFor()` ne lisait ni `expires_at` ni `status` — un forfait payant échu fonctionnait pour toujours.
| Mais il n'existait pas non plus de renouvellement : appliquer l'expiration d'un coup ferait retomber tous les abonnés payants
| au bout d'un mois. Donc : drapeau ÉTEINT par défaut (comportement inchangé), grâce de 7 jours, bouton « Renouveler »,
| renouvellement anticipé qui s'ajoute au reste, rapport en lecture seule, et AUCUNE suppression de données.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Billing\PlanService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Support\Billing\ExpiryNotice;
use Modules\Tagtoa\App\Support\Gateways\GatewayDriver;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class ExpiryPaidGateway implements GatewayDriver
{
    public function createPayment(PayTransaction $txn): ?string { return null; }

    public function verify(PayTransaction $txn): string { return 'paid'; }
}

class ExpiryCheckout extends CheckoutService
{
    protected function driver(string $gateway): ?GatewayDriver
    {
        return new ExpiryPaidGateway();
    }
}

class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlanService::flush();
        config(['tagtoa.enforce_expiry' => false, 'tagtoa.expiry_grace_days' => 7]);
    }

    protected function tearDown(): void
    {
        PlanService::flush();
        parent::tearDown();
    }

    private function abo(string $tenant, string $plan, ?\DateTimeInterface $fin, string $statut = 'active'): Subscription
    {
        return Subscription::allTenants()->create(['tenant_id' => $tenant, 'plan' => $plan, 'status' => $statut, 'started_at' => now()->subMonths(2), 'expires_at' => $fin]);
    }

    private function enforce(bool $on = true): void
    {
        config(['tagtoa.enforce_expiry' => $on]);
    }

    private function connecte(string $tenant): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
        Tenant::flush();
    }

    /* ---------- l'état ---------- */

    public function test_the_coverage_follows_the_end_date_and_the_grace_period(): void
    {
        $this->assertSame('active', $this->abo('t-a', 'pro', null)->coverage());
        $this->assertSame('active', $this->abo('t-b', 'pro', now()->addDays(20))->coverage());
        $this->assertSame('expiring', $this->abo('t-c', 'pro', now()->addDays(5))->coverage());
        $this->assertSame('grace', $this->abo('t-d', 'pro', now()->subDays(3))->coverage());
        $this->assertSame('expired', $this->abo('t-e', 'pro', now()->subDays(8))->coverage());
    }

    public function test_the_grace_period_follows_the_configuration(): void
    {
        $s = $this->abo('t-a', 'pro', now()->subDays(10));
        $this->assertSame('expired', $s->coverage());
        config(['tagtoa.expiry_grace_days' => 14]);
        $this->assertSame('grace', $s->coverage());
    }

    /* ---------- le forfait effectif ---------- */

    public function test_with_the_switch_off_an_expired_paid_plan_keeps_working_as_before(): void
    {
        $this->abo('t-1', 'pro', now()->subMonths(3));

        $this->assertSame('pro', Subscription::planFor('t-1'), 'Aucun changement tant que le drapeau est éteint.');
    }

    public function test_with_the_switch_on_a_plan_holds_until_the_grace_is_over_then_falls_back(): void
    {
        $this->enforce();
        $this->abo('t-null', 'pro', null);
        $this->abo('t-ok', 'pro', now()->addDays(20));
        $this->abo('t-soon', 'pro', now()->addDays(2));
        $this->abo('t-grace', 'pro', now()->subDays(3));
        $this->abo('t-expired', 'pro', now()->subDays(8));

        $this->assertSame('pro', Subscription::planFor('t-null'));
        $this->assertSame('pro', Subscription::planFor('t-ok'));
        $this->assertSame('pro', Subscription::planFor('t-soon'));
        $this->assertSame('pro', Subscription::planFor('t-grace'), 'En grâce : le forfait tient encore.');
        $this->assertSame('free', Subscription::planFor('t-expired'));
        $this->assertSame('free', Subscription::planFor('t-sans-abonnement'));
    }

    public function test_with_the_switch_on_a_non_active_status_loses_the_plan(): void
    {
        $this->enforce();
        $this->abo('t-1', 'pro', null, 'cancelled');

        $this->assertSame('free', Subscription::planFor('t-1'));
    }

    public function test_one_tenant_expiring_never_changes_another_tenants_plan(): void
    {
        $this->enforce();
        $this->abo('t-expire', 'pro', now()->subMonth());
        $this->abo('t-vivant', 'pro', now()->addMonth());

        $this->assertSame('free', Subscription::planFor('t-expire'));
        $this->assertSame('pro', Subscription::planFor('t-vivant'));
    }

    public function test_falling_back_only_blocks_new_creations_and_deletes_nothing(): void
    {
        $this->enforce();
        $this->abo('t-1', 'pro', now()->subMonth());
        for ($i = 0; $i < 3; $i++) {
            PaymentPage::create(['tenant_id' => 't-1', 'alias' => 'p-'.$i, 'title' => 'Page '.$i, 'default_currency' => 'HTG', 'is_active' => true]);
        }
        $plans = app(PlanService::class);

        $this->assertFalse($plans->canCreate('t-1', 'pay'), 'Limite du forfait gratuit : plus de NOUVELLE page.');
        $this->assertSame(3, PaymentPage::allTenants()->where('tenant_id', 't-1')->count(), 'Les pages existantes restent.');
    }

    /* ---------- renouvellement ---------- */

    private function payer(string $tenant, string $plan): void
    {
        $txn = PayTransaction::create(['tenant_id' => $tenant, 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'subscription', 'order_id' => 0, 'amount' => 1500, 'currency' => 'HTG', 'status' => PayTransaction::STATUS_PENDING,
            'meta' => ['plan' => $plan, 'tenant_id' => $tenant]]);
        $this->assertTrue((new ExpiryCheckout())->confirmOrFail($txn->reference));
    }

    public function test_an_early_renewal_of_the_same_plan_adds_a_month_to_what_remains(): void
    {
        $fin = now()->addDays(10)->startOfSecond();
        $this->abo('t-1', 'pro', $fin);

        $this->payer('t-1', 'pro');

        $nouvelle = Subscription::allTenants()->where('tenant_id', 't-1')->value('expires_at');
        $this->assertEquals($fin->copy()->addMonth()->timestamp, \Carbon\Carbon::parse($nouvelle)->timestamp, 'Le mois payé s\'ajoute au reste.');
    }

    public function test_renewing_after_expiry_starts_a_month_from_now_not_from_the_old_date(): void
    {
        $this->abo('t-1', 'pro', now()->subDays(20));

        $this->payer('t-1', 'pro');

        $nouvelle = \Carbon\Carbon::parse(Subscription::allTenants()->where('tenant_id', 't-1')->value('expires_at'));
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $nouvelle->timestamp, 5);
    }

    public function test_switching_plan_starts_a_month_from_now_and_never_inherits_the_old_plans_days(): void
    {
        $this->abo('t-1', 'pro', now()->addDays(25));

        $this->payer('t-1', 'reseller');

        $s = Subscription::allTenants()->where('tenant_id', 't-1')->first();
        $this->assertSame('reseller', $s->plan);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $s->expires_at->timestamp, 5);
    }

    public function test_a_replayed_settlement_does_not_extend_twice(): void
    {
        $this->abo('t-1', 'pro', now()->addDays(10));
        $txn = PayTransaction::create(['tenant_id' => 't-1', 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'subscription', 'order_id' => 0, 'amount' => 1500, 'currency' => 'HTG', 'status' => PayTransaction::STATUS_PENDING,
            'meta' => ['plan' => 'pro', 'tenant_id' => 't-1']]);

        (new ExpiryCheckout())->confirmOrFail($txn->reference);
        $apres1 = Subscription::allTenants()->where('tenant_id', 't-1')->value('expires_at');
        (new ExpiryCheckout())->confirmOrFail($txn->reference);

        $apres2 = Subscription::allTenants()->where('tenant_id', 't-1')->value('expires_at');
        $this->assertSame(\Carbon\Carbon::parse($apres1)->timestamp, \Carbon\Carbon::parse($apres2)->timestamp, 'Un rejeu ne prolonge pas deux fois.');
    }

    /* ---------- l'avis et le bouton ---------- */

    public function test_the_notice_says_expiring_always_and_grace_or_expired_only_when_enforced(): void
    {
        $this->abo('t-soon', 'pro', now()->addDays(3));
        $this->abo('t-grace', 'pro', now()->subDays(2));
        $this->abo('t-exp', 'pro', now()->subDays(9));
        $this->abo('t-ok', 'pro', now()->addDays(40));
        $this->abo('t-free', 'free', now()->addDays(2));
        $this->abo('t-null', 'pro', null);

        $this->assertSame('expiring', ExpiryNotice::forTenant('t-soon')['state']);
        $this->assertSame(3, ExpiryNotice::forTenant('t-soon')['days']);
        $this->assertNull(ExpiryNotice::forTenant('t-grace'), 'Interrupteur éteint : on ne promet pas une conséquence qui n\'existe pas.');
        $this->assertNull(ExpiryNotice::forTenant('t-exp'));

        $this->enforce();
        $this->assertSame('grace', ExpiryNotice::forTenant('t-grace')['state']);
        $this->assertSame(5, ExpiryNotice::forTenant('t-grace')['days'], '7 j de grâce − 2 j écoulés.');
        $this->assertSame('expired', ExpiryNotice::forTenant('t-exp')['state']);

        foreach (['t-ok', 't-free', 't-null', 'inconnu'] as $t) {
            $this->assertNull(ExpiryNotice::forTenant($t), $t);
        }
        $this->assertNull(ExpiryNotice::forTenant(null));
    }

    public function test_the_dashboard_shows_the_notice_and_the_plan_page_offers_renewal(): void
    {
        $this->abo('t-1', 'pro', now()->addDays(3));
        $this->connecte('t-1');

        $page = $this->get(route('tagtoa.plan.index'))->assertOk();
        $page->assertSee('expire dans 3 jour', false)->assertSee('Renouveler ce forfait', false);
    }

    public function test_a_healthy_subscription_shows_no_notice_and_no_renewal_button(): void
    {
        $this->abo('t-1', 'pro', now()->addDays(40));
        $this->connecte('t-1');

        $this->get(route('tagtoa.plan.index'))->assertOk()->assertDontSee('Renouveler ce forfait', false)->assertDontSee('expire dans', false)->assertSee('Forfait actuel', false);
    }

    public function test_subscribing_again_to_the_current_paid_plan_goes_to_payment_not_to_a_free_grant(): void
    {
        $this->abo('t-1', 'pro', now()->addDays(3));
        $this->connecte('t-1');
        $avant = \Carbon\Carbon::parse(Subscription::allTenants()->where('tenant_id', 't-1')->value('expires_at'))->timestamp;

        $this->post(route('tagtoa.plan.subscribe'), ['plan' => 'pro']);   // aucune passerelle active en test

        $this->assertSame($avant, \Carbon\Carbon::parse(Subscription::allTenants()->where('tenant_id', 't-1')->value('expires_at'))->timestamp, 'Sans paiement vérifié, rien ne se prolonge.');
    }

    /* ---------- le rapport ---------- */

    public function test_the_report_is_read_only_and_counts_who_would_fall_back(): void
    {
        $this->abo('t-soon', 'pro', now()->addDays(3));
        $this->abo('t-grace', 'pro', now()->subDays(2));
        $this->abo('t-exp1', 'pro', now()->subDays(40));
        $this->abo('t-exp2', 'reseller', now()->subDays(12));
        $this->abo('t-free', 'free', now()->subDays(100));
        $avant = Subscription::allTenants()->orderBy('id')->get(['tenant_id', 'plan', 'status', 'expires_at'])->toArray();

        $this->artisan('tagtoa:subscriptions:expiry-report')
            ->expectsOutputToContain('non (TAGTOA_ENFORCE_EXPIRY éteint)')
            ->expectsOutputToContain('bientôt échus 1 · en grâce 1 · échus 2')
            ->expectsOutputToContain('forfait gratuit si l\'expiration était appliquée : 2')
            ->expectsOutputToContain('t-exp1')
            ->assertSuccessful();

        $this->assertSame($avant, Subscription::allTenants()->orderBy('id')->get(['tenant_id', 'plan', 'status', 'expires_at'])->toArray(), 'Lecture seule.');
    }

    public function test_the_deploy_script_runs_the_report_without_ever_blocking(): void
    {
        $sh = file_get_contents(dirname(__DIR__, 2).'/deploy/remote-deploy.sh');

        $this->assertStringContainsString('tagtoa:subscriptions:expiry-report', $sh);
        $this->assertMatchesRegularExpression('/expiry-report[^\n]*\|\| echo/', $sh, 'Le rapport ne doit jamais faire échouer le déploiement.');
    }

    public function test_the_switch_is_off_by_default_in_the_shipped_configuration(): void
    {
        $cfg = require dirname(__DIR__, 2).'/config/config.php';

        $this->assertFalse($cfg['enforce_expiry'], 'Livré ÉTEINT : on l\'allume après avoir lu le rapport.');
        $this->assertSame(7, $cfg['expiry_grace_days']);
    }
}
