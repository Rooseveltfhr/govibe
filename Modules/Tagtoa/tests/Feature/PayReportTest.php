<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — rapport d'un lien : revenu/preuves par période, conversion
| depuis toujours.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Services\Pay\PayReportService;
use Modules\Tagtoa\Tests\TestCase;

class PayReportTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function page(string $tenant = 't-1'): PaymentPage
    {
        return PaymentPage::create([
            'tenant_id' => $tenant, 'alias' => 'boutique-'.random_int(1000, 9999), 'title' => 'Boutique',
            'default_currency' => 'HTG', 'is_active' => true, 'views' => 100,
        ]);
    }

    private function methode(string $tenant = 't-1'): PaymentMethod
    {
        return PaymentMethod::create([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id,
            'tenant_id' => $tenant, 'type' => 'moncash', 'is_active' => true,
        ]);
    }

    private function preuve(PaymentPage $page, PaymentMethod $method, float $amount, int $status, string $payer = 'Client'): PaymentProof
    {
        return PaymentProof::create([
            'payment_page_id' => $page->id, 'payment_method_id' => $method->id,
            'payer_name' => $payer, 'amount' => $amount, 'currency' => 'HTG', 'status' => $status,
        ]);
    }

    public function test_le_rapport_totalise_les_preuves_par_statut(): void
    {
        $page = $this->page();
        $method = $this->methode();
        $this->preuve($page, $method, 100, PaymentProof::STATUS_APPROVED, 'A');
        $this->preuve($page, $method, 50, PaymentProof::STATUS_APPROVED, 'B');
        $this->preuve($page, $method, 30, PaymentProof::STATUS_PENDING, 'C');
        $this->preuve($page, $method, 20, PaymentProof::STATUS_REJECTED, 'D');

        $period = app(PayReportService::class)->forPeriod($page, now()->subDays(1), now());

        $this->assertEquals(150.0, $period['revenue']);
        $this->assertSame(2, $period['approved_count']);
        $this->assertSame(1, $period['pending_count']);
        $this->assertSame(1, $period['rejected_count']);
    }

    public function test_une_preuve_hors_periode_nest_pas_comptee(): void
    {
        $page = $this->page();
        $vieille = $this->preuve($page, $this->methode(), 999, PaymentProof::STATUS_APPROVED, 'Ancien');
        $vieille->forceFill(['created_at' => now()->subDays(60)])->save();

        $period = app(PayReportService::class)->forPeriod($page, now()->subDays(29), now());

        $this->assertEquals(0.0, $period['revenue']);
    }

    public function test_la_conversion_ignore_les_preuves_refusees(): void
    {
        $page = $this->page();
        $method = $this->methode();
        $this->preuve($page, $method, 100, PaymentProof::STATUS_APPROVED, 'A');
        $this->preuve($page, $method, 100, PaymentProof::STATUS_REJECTED, 'B');

        $conversion = app(PayReportService::class)->conversion($page);

        $this->assertSame(1, $conversion['approved']);
        $this->assertSame(100, $conversion['views']);
        $this->assertSame(1.0, $conversion['rate']);
    }

    public function test_zero_vue_ne_produit_pas_un_taux_trompeur(): void
    {
        $page = PaymentPage::create([
            'tenant_id' => 't-1', 'alias' => 'neuve-'.random_int(1000, 9999), 'title' => 'Neuve',
            'default_currency' => 'HTG', 'is_active' => true, 'views' => 0,
        ]);

        $conversion = app(PayReportService::class)->conversion($page);

        $this->assertNull($conversion['rate']);
    }

    public function test_lecran_est_cloisonne_par_commerce(): void
    {
        $this->patron('t-1');
        $autre = $this->page('t-2');

        $this->get(route('tagtoa.pay.dashboard.report', $autre->id))->assertNotFound();
    }

    public function test_lecran_affiche_le_rapport(): void
    {
        $this->patron();
        $page = $this->page();
        $this->preuve($page, $this->methode(), 250, PaymentProof::STATUS_APPROVED, 'A');

        $this->get(route('tagtoa.pay.dashboard.report', $page->id))
            ->assertOk()
            ->assertSee('250');
    }
}
