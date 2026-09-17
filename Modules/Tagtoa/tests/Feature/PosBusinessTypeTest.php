<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — multi-canal : hôtel, bar, restaurant, clinique, pharmacie,
| boutique doivent tous pouvoir fonctionner dans la même caisse. Le type
| d'activité (Business::type) se choisit depuis les réglages POS, et les
| unités proposées au formulaire produit s'y adaptent — sans jamais en
| interdire aucune, car un commerce réel vend rarement une seule sorte
| d'article (une pharmacie vend aussi des biberons à la pièce).
|--------------------------------------------------------------------------
*/
class PosBusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $accountId = 't-1', string $type = 'other'): Business
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $accountId, 'name' => 'Roosevelt']));
        Tenant::flush();

        return app(BusinessService::class)->create($accountId, ['name' => 'Mon commerce', 'type' => $type]);
    }

    private function caisse(string $tenantId): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    /* ------------------------------------------------------------------
       Choisir le type d'activité depuis la caisse.
       ------------------------------------------------------------------ */

    public function test_the_settings_screen_lets_the_owner_pick_the_business_type(): void
    {
        $business = $this->patron('t-1', 'other');

        $this->put(route('tagtoa.pos.settings.business-type'), ['type' => 'pharmacy'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('pharmacy', $business->fresh()->type);
    }

    public function test_an_invented_activity_type_is_refused(): void
    {
        $this->patron('t-1', 'other');

        $this->put(route('tagtoa.pos.settings.business-type'), ['type' => 'spatioport'])
            ->assertSessionHasErrors('type');
    }

    public function test_changing_the_type_never_touches_another_tenants_business(): void
    {
        $voisin = app(BusinessService::class)->create('t-voisin', ['name' => 'Voisin', 'type' => 'bar']);
        $this->patron('t-1', 'other');

        $this->put(route('tagtoa.pos.settings.business-type'), ['type' => 'pharmacy']);

        $this->assertSame('bar', $voisin->fresh()->type, 'Le commerce du voisin ne doit pas bouger.');
    }

    public function test_the_settings_screen_shows_the_current_activity_type(): void
    {
        $this->patron('t-1', 'bar');

        $this->get(route('tagtoa.pos.settings'))
            ->assertOk()
            ->assertSee('selected', false)
            ->assertSee(__('Bar'));
    }

    /* ------------------------------------------------------------------
       Les unités s'adaptent, sans jamais rien interdire.
       ------------------------------------------------------------------ */

    public function test_a_pharmacy_sees_tablet_and_blister_units_suggested_first(): void
    {
        $business = $this->patron('t-1', 'pharmacy');
        $caisse = $this->caisse($business->id);

        $html = $this->get(route('tagtoa.pos.products.terminal', $caisse->id))->assertOk()->getContent();

        $posSuggerees = strpos($html, 'Suggérées pour votre activité');
        $posComprime = strpos($html, 'value="comprime"');
        $posShot = strpos($html, 'value="shot"');

        $this->assertNotFalse($posSuggerees);
        $this->assertNotFalse($posComprime);
        // Le comprimé (suggéré pour une pharmacie) apparaît avant le shot
        // (unité de bar, reléguée à « Autres unités »).
        $this->assertLessThan($posShot, $posComprime);
    }

    public function test_a_bar_sees_bottle_and_glass_units_suggested_first(): void
    {
        $business = $this->patron('t-1', 'bar');
        $caisse = $this->caisse($business->id);

        $html = $this->get(route('tagtoa.pos.products.terminal', $caisse->id))->assertOk()->getContent();

        $posBouteille = strpos($html, 'value="bouteille"');
        // « kg » précède « bouteille » dans la liste NATURELLE de Pricing::UNITS
        // — si l'ordre affiché n'était pas vraiment recalculé pour ce type, ce
        // test passerait par accident. En comparant à une unité placée AVANT
        // dans la liste brute, on vérifie que le réordonnancement a bien lieu.
        $posKg = strpos($html, 'value="kg"');

        $this->assertNotFalse($posBouteille);
        $this->assertNotFalse($posKg);
        $this->assertLessThan($posKg, $posBouteille);
    }

    public function test_every_unit_stays_selectable_whatever_the_business_type(): void
    {
        // Une SUGGESTION, jamais une restriction : une pharmacie qui vend
        // aussi des biberons à la pièce ne doit pas être bloquée, et un bar
        // pourrait très bien vendre des glaçons au sac.
        $business = $this->patron('t-1', 'pharmacy');
        $caisse = $this->caisse($business->id);

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Glaçons', 'price' => 50, 'unit' => 'sac',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('sac', Product::where('name', 'Glaçons')->value('unit'));
    }

    public function test_a_business_type_never_seen_before_does_not_crash_the_product_screen(): void
    {
        // Un commerce sans type déclaré (compte très ancien, ou Business
        // absent) doit quand même afficher un formulaire produit utilisable.
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-sans-business', 'name' => 'Roosevelt']));
        Tenant::flush();
        $caisse = $this->caisse('t-sans-business');

        $this->get(route('tagtoa.pos.products.terminal', $caisse->id))->assertOk();
    }
}
