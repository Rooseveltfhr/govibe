<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandClaimAttempt;
use Modules\Tagtoa\App\Services\Stand\StandClaimService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Services\Stand\StandResolver;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Scanner un stand, puis le réclamer.
 *
 * Deux exigences gouvernent tout ce fichier :
 *   • un client attablé ne doit JAMAIS voir une panne ;
 *   • personne ne doit pouvoir réclamer le stand d'un autre.
 */
class StandClaimTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function fabriquer(int $n = 1): void
    {
        $r = app(StandMinter::class)->mint('TAGTOA-2026-001', $n);
        $this->secrets = $r['secrets'];
    }

    private function code(Stand $stand): string
    {
        return $this->secrets[$stand->public_id];
    }

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function claims(): StandClaimService
    {
        return app(StandClaimService::class);
    }

    /* ------------------------------------------------------------------
       Le scan : ce que voit un client.
       ------------------------------------------------------------------ */

    public function test_an_unactivated_stand_invites_its_owner_to_activate(): void
    {
        $this->fabriquer();

        $this->get('/s/'.Stand::first()->public_id)
            ->assertOk()
            ->assertSee('pas encore activé');
    }

    public function test_an_unknown_number_never_looks_like_a_crash(): void
    {
        // Un client attablé ne doit pas voir une erreur 404 : il repose son
        // téléphone, et c'est le commerçant qui en paie le prix.
        $this->get('/s/TG-999999')->assertOk()->assertSee('aucun stand');
        $this->get('/s/n-importe-quoi')->assertOk();
    }

    public function test_a_malformed_number_answers_exactly_like_a_missing_one(): void
    {
        // Les distinguer apprendrait à l'attaquant à quoi ressemble un
        // identifiant valide.
        $absent = $this->get('/s/TG-999999')->getContent();
        $malforme = $this->get('/s/ABC')->getContent();

        $this->assertSame(
            substr_count($absent, 'aucun stand'),
            substr_count($malforme, 'aucun stand')
        );
    }

    public function test_an_active_stand_goes_straight_to_the_menu(): void
    {
        // Chaque saut coûte une seconde sur une connexion faible : pas de page
        // intermédiaire.
        $this->fabriquer();
        Menu::create(['tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'lakay', 'currency' => 'HTG', 'is_active' => true]);

        Stand::first()->update([
            'tenant_id' => 't-1', 'digital_state' => StandState::ACTIVE, 'target_module' => 'menu',
        ]);

        $this->get('/s/'.Stand::first()->public_id)->assertRedirect(url('/menu/lakay'));
    }

    public function test_a_suspended_stand_never_shows_a_paywall(): void
    {
        // Le client a faim. On prévient le commerçant, pas son client.
        $this->fabriquer();
        Stand::first()->update(['tenant_id' => 't-1', 'digital_state' => StandState::SUSPENDED]);

        $reponse = $this->get('/s/'.Stand::first()->public_id)->assertOk();

        $reponse->assertSee('pas disponible');
        $reponse->assertDontSee('abonnement');
        $reponse->assertDontSee('payer');
    }

    public function test_an_active_stand_whose_menu_vanished_stays_dignified(): void
    {
        // Menu supprimé, commerce fermé : pas de 404 au visage du client.
        $this->fabriquer();
        Stand::first()->update(['tenant_id' => 't-1', 'digital_state' => StandState::ACTIVE]);

        $this->get('/s/'.Stand::first()->public_id)->assertOk()->assertSee('pas disponible');
    }

    public function test_the_public_status_reveals_nothing_about_the_business(): void
    {
        // Cette route est ouverte : un attaquant qui balaie ne doit rien
        // apprendre de plus que « activable ou non ».
        $this->fabriquer();
        Menu::create(['tenant_id' => 't-1', 'name' => 'Chez Lakay', 'alias' => 'lakay', 'currency' => 'HTG', 'is_active' => true]);
        Stand::first()->update([
            'tenant_id' => 't-1', 'digital_state' => StandState::ACTIVE, 'location_label' => 'Table 05',
        ]);

        $reponse = $this->getJson('/s/'.Stand::first()->public_id.'/status')->assertOk();

        $reponse->assertJsonPath('active', true);
        $reponse->assertJsonMissing(['tenant_id' => 't-1']);
        $this->assertStringNotContainsString('Table 05', $reponse->getContent());
        $this->assertStringNotContainsString('lakay', $reponse->getContent());
        $this->assertStringNotContainsString('TAGTOA-2026', $reponse->getContent());
    }

    /* ------------------------------------------------------------------
       Vérifier le code — sans compte.
       ------------------------------------------------------------------ */

    public function test_the_code_is_checked_before_any_account_is_created(): void
    {
        // Faire créer un compte pour annoncer ensuite que le code est illisible
        // est la façon la plus sûre de perdre quelqu'un.
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->claims()->verify($stand->public_id, $this->code($stand));

        $this->assertSame(StandClaimService::OK, $r['result']);
        $this->assertNotNull($r['token']);
        // Aucun compte n'a été exigé pour arriver jusqu'ici.
        $this->assertNull(auth()->id(), 'Le code se vérifie sans compte.');
    }

    public function test_a_wrong_code_says_so_without_touching_the_stand(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->claims()->verify($stand->public_id, 'A3F9K2MP');

        $this->assertSame(StandClaimService::BAD_CODE, $r['result']);
        $this->assertSame(StandState::UNCLAIMED, $stand->fresh()->digital_state);
    }

    public function test_an_unknown_stand_and_a_wrong_code_are_indistinguishable(): void
    {
        $this->fabriquer();

        $this->assertNull($this->claims()->verify('TG-999999', 'A3F9K2MP')['token']);
        $this->assertNull($this->claims()->verify(Stand::first()->public_id, 'A3F9K2MP')['token']);
    }

    public function test_ten_wrong_tries_lock_the_stand_for_an_hour(): void
    {
        // Limite PAR STAND : une limite par IP se contourne avec un réseau de
        // proxys, celle-ci vaut quel que soit le nombre de machines.
        $this->fabriquer();
        $stand = Stand::first();

        for ($i = 0; $i < StandClaimService::MAX_ATTEMPTS; $i++) {
            $this->claims()->verify($stand->public_id, 'A3F9K2MP');
        }

        // Même le BON code est refusé une fois le seuil franchi.
        $r = $this->claims()->verify($stand->public_id, $this->code($stand));

        $this->assertSame(StandClaimService::TOO_MANY, $r['result']);
    }

    public function test_the_tried_code_is_never_written_down(): void
    {
        // Une frappe malheureuse peut être le code d'un stand voisin : le
        // journal deviendrait une liste de codes valides en clair.
        $this->fabriquer();
        $stand = Stand::first();
        $code = $this->code($stand);

        $this->claims()->verify($stand->public_id, $code);

        foreach (StandClaimAttempt::all() as $t) {
            $this->assertStringNotContainsString($code, json_encode($t->toArray()));
        }
    }

    /* ------------------------------------------------------------------
       Réclamer.
       ------------------------------------------------------------------ */

    public function test_a_verified_stand_becomes_the_property_of_the_business(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $v = $this->claims()->verify($stand->public_id, $this->code($stand));
        $r = $this->claims()->claim($stand->public_id, $v['token'], 't-1', ['location_label' => 'Table 05']);

        $frais = $stand->fresh();

        $this->assertSame(StandClaimService::OK, $r['result']);
        $this->assertSame('t-1', $frais->tenant_id);
        $this->assertSame(StandState::ACTIVE, $frais->digital_state);
        $this->assertSame(StandState::SOLD, $frais->physical_state);
        $this->assertSame('Table 05', $frais->location_label);
        $this->assertNotNull($frais->claimed_at);
    }

    public function test_the_reservation_token_cannot_be_used_twice(): void
    {
        // Rejouer une réclamation transférerait le stand à qui rejoue.
        $this->fabriquer();
        $stand = Stand::first();

        $v = $this->claims()->verify($stand->public_id, $this->code($stand));
        $this->claims()->claim($stand->public_id, $v['token'], 't-1');

        $second = $this->claims()->claim($stand->public_id, $v['token'], 't-2');

        $this->assertSame(StandClaimService::NOT_OPEN, $second['result']);
        $this->assertSame('t-1', $stand->fresh()->tenant_id, 'Le stand doit rester à son premier propriétaire.');
    }

    public function test_a_forged_token_claims_nothing(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $this->claims()->verify($stand->public_id, $this->code($stand));
        $r = $this->claims()->claim($stand->public_id, str_repeat('a', 48), 't-2');

        $this->assertSame(StandClaimService::NOT_OPEN, $r['result']);
        $this->assertNull($stand->fresh()->tenant_id);
    }

    public function test_an_already_claimed_stand_cannot_be_taken(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $v = $this->claims()->verify($stand->public_id, $this->code($stand));
        $this->claims()->claim($stand->public_id, $v['token'], 't-1');

        // Le voisin connaît le code — il a vu le stand — mais il est trop tard.
        $r = $this->claims()->verify($stand->public_id, $this->code($stand));

        $this->assertSame(StandClaimService::NOT_OPEN, $r['result']);
        $this->assertSame('t-1', $stand->fresh()->tenant_id);
    }

    public function test_two_people_grating_the_same_carton_do_not_both_win(): void
    {
        // Le premier réserve ; le second doit attendre, pas écraser.
        $this->fabriquer();
        $stand = Stand::first();

        $premier = $this->claims()->verify($stand->public_id, $this->code($stand));
        $second  = $this->claims()->verify($stand->public_id, $this->code($stand));

        $this->assertSame(StandClaimService::OK, $premier['result']);
        $this->assertSame(StandClaimService::RESERVED, $second['result']);
        $this->assertNull($second['token']);
    }

    public function test_an_abandoned_activation_frees_the_stand(): void
    {
        // Un client qui abandonne à mi-parcours ne doit pas condamner un objet
        // qu'il a payé.
        $this->fabriquer();
        $stand = Stand::first();

        $this->claims()->verify($stand->public_id, $this->code($stand));
        $stand->fresh()->update(['claim_reserved_until' => now()->subMinutes(1)]);

        $this->assertTrue($this->claims()->releaseExpired($stand->fresh()));
        $this->assertSame(StandState::UNCLAIMED, $stand->fresh()->digital_state);

        // Et il redevient réclamable pour de bon.
        $this->assertSame(StandClaimService::OK,
            $this->claims()->verify($stand->public_id, $this->code($stand))['result']);
    }

    public function test_a_running_reservation_is_never_released(): void
    {
        $this->fabriquer();
        $stand = Stand::first();
        $this->claims()->verify($stand->public_id, $this->code($stand));

        $this->assertFalse($this->claims()->releaseExpired($stand->fresh()));
    }

    public function test_a_lost_stand_cannot_be_claimed_even_with_its_code(): void
    {
        $this->fabriquer();
        $stand = Stand::first();
        $stand->update(['physical_state' => StandState::LOST]);

        $this->assertSame(StandClaimService::NOT_OPEN,
            $this->claims()->verify($stand->public_id, $this->code($stand))['result']);
    }

    /* ------------------------------------------------------------------
       Le cache : ce qui ferait servir hier au client d'aujourd'hui.
       ------------------------------------------------------------------ */

    public function test_claiming_a_stand_changes_where_it_leads_at_once(): void
    {
        $this->fabriquer();
        $stand = Stand::first();
        Menu::create(['tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'lakay', 'currency' => 'HTG', 'is_active' => true]);

        // Mis en cache comme « à activer ».
        $this->get('/s/'.$stand->public_id)->assertOk()->assertSee('pas encore activé');

        $v = $this->claims()->verify($stand->public_id, $this->code($stand));
        $this->claims()->claim($stand->public_id, $v['token'], 't-1');

        // Sans invalidation, le client verrait encore la page d'activation.
        $this->get('/s/'.$stand->public_id)->assertRedirect(url('/menu/lakay'));
    }

    public function test_moving_a_stand_to_another_table_takes_effect_at_once(): void
    {
        $this->fabriquer();
        $stand = Stand::first();
        $stand->update(['tenant_id' => 't-1', 'digital_state' => StandState::ACTIVE]);
        Menu::create(['tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'lakay', 'currency' => 'HTG', 'is_active' => true]);

        $this->get('/s/'.$stand->public_id);

        $this->patron('t-1');
        $this->put(route('tagtoa.stand.update', $stand->id), ['location_label' => 'Table 12'])
            ->assertRedirect();

        $this->assertSame('Table 12', $stand->fresh()->location_label);
        $this->assertSame('Table 12', app(StandResolver::class)->resolve($stand->public_id)['label'],
            'Le cache doit avoir été vidé.');
    }

    /* ------------------------------------------------------------------
       Cloisonnement.
       ------------------------------------------------------------------ */

    public function test_a_merchant_only_sees_his_own_stands(): void
    {
        // Le modèle n'a pas de portée automatique : le cloisonnement s'écrit à
        // la main, donc il doit être testé à la main.
        $this->fabriquer(2);
        $stands = Stand::orderBy('serial')->get();
        $stands[0]->update(['tenant_id' => 't-1', 'digital_state' => StandState::ACTIVE]);
        $stands[1]->update(['tenant_id' => 't-2', 'digital_state' => StandState::ACTIVE]);

        $this->patron('t-1');

        $this->get(route('tagtoa.stand.index'))
            ->assertOk()
            ->assertSee($stands[0]->public_id)
            ->assertDontSee($stands[1]->public_id);
    }

    public function test_the_neighbour_cannot_rename_my_stand(): void
    {
        $this->fabriquer();
        $stand = Stand::first();
        $stand->update(['tenant_id' => 't-1', 'digital_state' => StandState::ACTIVE, 'location_label' => 'Table 05']);

        $this->patron('t-2');

        $this->put(route('tagtoa.stand.update', $stand->id), ['location_label' => 'Détourné'])
            ->assertNotFound();

        $this->assertSame('Table 05', $stand->fresh()->location_label);
    }

    /* ------------------------------------------------------------------
       Le parcours complet, par HTTP.
       ------------------------------------------------------------------ */

    public function test_the_whole_journey_from_scan_to_owned(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        // 1. Le client scanne : on lui propose d'activer.
        $this->get('/s/'.$stand->public_id)->assertOk()->assertSee('pas encore activé');

        // 2. Il tape le code gratté — sans compte.
        $this->post('/s/'.$stand->public_id.'/verify', ['code' => $this->code($stand)])
            ->assertRedirect(route('tagtoa.stand.claim.form', $stand->public_id));

        // 3. Il crée son compte, puis réclame.
        $this->patron('t-1');
        $this->post(route('tagtoa.stand.claim', $stand->public_id), ['location_label' => 'Table 05'])
            ->assertRedirect(route('tagtoa.stand.index'));

        $frais = $stand->fresh();
        $this->assertSame('t-1', $frais->tenant_id);
        $this->assertSame(StandState::ACTIVE, $frais->digital_state);
    }

    public function test_the_claim_form_refuses_to_open_without_a_verified_code(): void
    {
        // Montrer un formulaire qui échouera de toute façon fait perdre du
        // temps à quelqu'un qui n'a rien fait de mal.
        $this->fabriquer();
        $this->patron('t-1');

        $this->get(route('tagtoa.stand.claim.form', Stand::first()->public_id))
            ->assertRedirect(route('tagtoa.stand.show', Stand::first()->public_id));
    }

    public function test_a_wrong_code_over_http_says_what_to_do(): void
    {
        $this->fabriquer();

        $this->post('/s/'.Stand::first()->public_id.'/verify', ['code' => 'A3F9K2MP'])
            ->assertSessionHasErrors('code');
    }

    /* ------------------------------------------------------------------
       L'activité : dire la vérité sur ce qu'on mesure.
       ------------------------------------------------------------------ */

    public function test_a_scan_leaves_a_trace_without_writing_on_every_request(): void
    {
        // Compter chaque scan écrirait dans la base sur la requête la plus
        // fréquente de la plateforme — ce qui annulerait le bénéfice du cache.
        // On note une fois par période, pas une fois par client.
        $this->fabriquer();
        $stand = Stand::first();

        $this->get('/s/'.$stand->public_id);
        $apresPremier = $stand->fresh();

        $this->assertSame(1, $apresPremier->scan_count);
        $this->assertNotNull($apresPremier->last_scanned_at);

        // Les scans suivants sont servis depuis le cache : aucune écriture.
        $this->get('/s/'.$stand->public_id);
        $this->get('/s/'.$stand->public_id);

        $this->assertSame(1, $stand->fresh()->scan_count,
            'Le compteur mesure des périodes d\'activité, pas des clients.');
    }

    public function test_an_unknown_stand_never_writes_anything(): void
    {
        // Un balayage d'identifiants ne doit pas faire grossir une table.
        $this->fabriquer();

        $this->get('/s/TG-999999');

        $this->assertSame(0, (int) Stand::sum('scan_count'));
    }
}
