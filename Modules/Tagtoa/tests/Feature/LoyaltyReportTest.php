<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\Tests\TestCase;

/**
 * LE RAPPORT DE FIDÉLISATION — ce qui a bougé sur une période, et qui compose
 * la base de cartes aujourd'hui (segments). Les deux sont volontairement
 * indépendants : voir LoyaltyReportService.
 */
class LoyaltyReportTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function programme(string $tenantId = 't-1'): Program
    {
        return Program::create([
            'tenant_id' => $tenantId,
            'name' => 'Programme',
            'alias' => 'programme-'.$tenantId.'-'.random_int(1000, 9999),
            'points_per_dollar' => 1,
            'currency' => 'HTG',
            'is_active' => true,
        ]);
    }

    private function carte(Program $program, array $attrs = []): Card
    {
        return app(LoyaltyCardService::class)->issueCard($program, array_merge([
            'cardholder_name' => 'Cliente',
        ], $attrs))['card'];
    }

    public function test_le_rapport_totalise_les_mouvements_de_la_periode(): void
    {
        $this->patron();
        $program = $this->programme();
        $card = $this->carte($program);

        $service = app(LoyaltyCardService::class);
        $service->topUp($card, 100);
        $service->redeem($card, 30);

        $response = $this->get(route('tagtoa.loyalty.dashboard.report', $program->id));

        $response->assertOk();
        $response->assertViewHas('period', function (array $period) {
            return $period['amount_topped_up'] === 100.0
                && $period['amount_redeemed'] === 30.0
                && $period['top_up_count'] === 1
                && $period['redeem_count'] === 1;
        });
    }

    public function test_une_carte_jamais_utilisee_et_ancienne_compte_inactive(): void
    {
        $this->patron();
        $program = $this->programme();
        $card = $this->carte($program);
        // Émise il y a longtemps, jamais aucun mouvement.
        $card->forceFill(['issued_at' => Carbon::now()->subDays(200)])->save();

        $response = $this->get(route('tagtoa.loyalty.dashboard.report', $program->id));

        $response->assertOk();
        $response->assertViewHas('segments', function (array $segments) {
            return $segments['inactif'] === 1 && $segments['nouveau'] === 0 && $segments['vip'] === 0;
        });
    }

    public function test_une_carte_recente_et_a_forts_points_reste_nouvelle_pas_vip(): void
    {
        $this->patron();
        $program = $this->programme();
        $card = $this->carte($program);
        $card->forceFill(['issued_at' => Carbon::now()->subDays(2), 'points' => 900])->save();

        $response = $this->get(route('tagtoa.loyalty.dashboard.report', $program->id));

        $response->assertViewHas('segments', fn (array $s) => $s['nouveau'] === 1 && $s['vip'] === 0);
    }

    public function test_le_rapport_dun_autre_commerce_est_hors_de_portee(): void
    {
        $this->patron('t-1');
        $autre = $this->programme('t-2');

        $this->get(route('tagtoa.loyalty.dashboard.report', $autre->id))->assertNotFound();
    }

    public function test_les_bornes_inversees_sont_reordonnees_sans_erreur(): void
    {
        $this->patron();
        $program = $this->programme();

        $response = $this->get(route('tagtoa.loyalty.dashboard.report', $program->id).'?from=2026-01-31&to=2026-01-01');

        $response->assertOk();
    }
}
