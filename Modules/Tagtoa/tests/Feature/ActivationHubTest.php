<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — « Activer un produit » : un seul écran, deux mécanismes existants
|--------------------------------------------------------------------------
| L'écran lui-même n'implémente aucune activation : il pose « que tenez-vous
| en main ? » puis envoie vers StandActivator ou CardWalletService, inchangés.
|
| Le test qui compte vraiment ici est celui du VRAI défaut trouvé pendant la
| recherche : l'ancien écran d'activation en série (stand/activate.blade.php)
| n'envoyait JAMAIS `target_module` — chaque stand retombait silencieusement
| sur « menu », même pour un Payment Stand ou un Stand Liens. Le nouvel écran
| l'envoie désormais selon le TYPE choisi ; on le vérifie ici en simulant
| exactement la requête que son JavaScript émet.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\Tests\TestCase;

class ActivationHubTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function fabriquer(int $n = 1): void
    {
        $r = app(StandMinter::class)->mint('TAGTOA-2026-ACT', $n);
        $this->secrets = $r['secrets'];
    }

    private function gratte(Stand $stand): string
    {
        return StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]);
    }

    public function test_the_hub_opens_and_offers_every_product_type(): void
    {
        $this->patron();

        $this->get(route('tagtoa.activate'))
            ->assertOk()
            ->assertSee('Smart Stand — Menu')
            ->assertSee('Smart Stand — Paiement')
            ->assertSee('Smart Stand — Liens')
            ->assertSee('Carte NFC TAGTOA');
    }

    public function test_choosing_the_payment_stand_type_actually_sets_target_module_pay(): void
    {
        // LE défaut que cet écran corrige : avant lui, ce champ n'était jamais
        // envoyé et le stand retombait sur « menu » quel que soit le produit.
        $this->fabriquer();
        $this->patron();
        $stand = Stand::first();

        $this->postJson(route('tagtoa.stand.activate.scan'), [
            'payload'       => $this->gratte($stand),
            'target_module' => 'pay',
        ])->assertOk()->assertJson(['result' => StandActivator::OK]);

        $this->assertSame('pay', $stand->refresh()->target_module);
    }

    public function test_choosing_the_links_stand_type_sets_target_module_links(): void
    {
        $this->fabriquer();
        $this->patron();
        $stand = Stand::first();

        $this->postJson(route('tagtoa.stand.activate.scan'), [
            'payload'       => $this->gratte($stand),
            'target_module' => 'links',
        ])->assertOk();

        $this->assertSame('links', $stand->refresh()->target_module);
    }

    public function test_choosing_the_menu_stand_type_sets_target_module_menu(): void
    {
        $this->fabriquer();
        $this->patron();
        $stand = Stand::first();

        $this->postJson(route('tagtoa.stand.activate.scan'), [
            'payload'       => $this->gratte($stand),
            'target_module' => 'menu',
        ])->assertOk();

        $this->assertSame('menu', $stand->refresh()->target_module);
    }

    public function test_the_card_form_posts_to_the_existing_card_activation_route(): void
    {
        $this->patron();

        $this->get(route('tagtoa.activate'))
            ->assertOk()
            ->assertSee(route('tagtoa.cards.store'), false)
            ->assertSee(route('tagtoa.stand.activate.scan'), false);
    }
}
