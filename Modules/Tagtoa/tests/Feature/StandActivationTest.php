<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandClaimAttempt;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Services\Stand\StandClaimService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\Tests\TestCase;

/**
 * M4 — quarante tables, quatre minutes.
 *
 * Deux exigences gouvernent ce fichier, et elles tirent en sens opposé :
 *   • activer une salle entière doit être RAPIDE, sinon les stands restent
 *     dans le carton et le marchand s'en va ;
 *   • personne ne doit pouvoir activer le stand d'un autre, et la rapidité ne
 *     doit rien retirer à cette garantie.
 */
class StandActivationTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function fabriquer(int $n = 1): void
    {
        $r = app(StandMinter::class)->mint('TAGTOA-2026-001', $n);
        $this->secrets = $r['secrets'];
    }

    /** La charge utile telle qu'elle est imprimée sous le panneau à gratter. */
    private function gratte(Stand $stand): string
    {
        return StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]);
    }

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function activateur(): StandActivator
    {
        return app(StandActivator::class);
    }

    /* ------------------------------------------------------------------
       Le geste : une lecture, un stand activé.
       ------------------------------------------------------------------ */

    public function test_one_scan_activates_one_stand(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->activateur()->activate($this->gratte($stand), 't-1');

        $this->assertSame(StandActivator::OK, $r['result']);

        $stand->refresh();
        $this->assertSame('t-1', $stand->tenant_id);
        $this->assertSame(StandState::ACTIVE, $stand->digital_state);
        $this->assertSame(StandState::SOLD, $stand->physical_state);
        $this->assertNotNull($stand->claimed_at);
    }

    public function test_a_whole_room_is_activated_scan_after_scan(): void
    {
        // Le cas qui justifie tout le module : quarante tables d'affilée, sans
        // repasser par la création de compte ni par une réservation.
        $this->fabriquer(40);
        $this->patron();

        foreach (Stand::orderBy('serial')->get() as $stand) {
            $r = $this->activateur()->activate($this->gratte($stand), 't-1');
            $this->assertSame(StandActivator::OK, $r['result'], "Échec sur {$stand->public_id}");
        }

        $this->assertSame(40, Stand::ofBusiness('t-1')->count());
    }

    public function test_the_two_step_dance_is_not_required_once_signed_in(): void
    {
        // M3 fait VÉRIFIER puis RÉCLAMER parce que le compte n'existe pas
        // encore. Au stand numéro deux, il existe : redemander une réservation
        // de quinze minutes et un jeton de session serait du cérémonial pur.
        $this->fabriquer();
        $stand = Stand::first();

        $this->activateur()->activate($this->gratte($stand), 't-1');

        $stand->refresh();
        $this->assertNull($stand->claim_reserved_token,
            'Aucune réservation ne doit subsister : un jeton vivant se rejoue.');
        $this->assertNull($stand->claim_reserved_until);
    }

    /* ------------------------------------------------------------------
       Ce qui n'est PAS assoupli.
       ------------------------------------------------------------------ */

    public function test_the_front_qr_alone_never_activates_anything(): void
    {
        // LE test du module. Le QR du recto est photographiable — c'est acquis
        // depuis M2. Si l'identifiant seul suffisait, n'importe quel passant
        // réclamerait les stands d'un restaurant depuis le trottoir.
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->activateur()->activate('https://tagtoa.com/s/'.$stand->public_id, 't-1');

        $this->assertSame(StandActivator::NO_SECRET, $r['result']);
        $this->assertNull($stand->refresh()->tenant_id);
    }

    public function test_a_wrong_secret_activates_nothing(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->activateur()->activate($stand->public_id.'-AAAAAAAA', 't-1');

        $this->assertSame(StandActivator::BAD_CODE, $r['result']);
        $this->assertNull($stand->refresh()->tenant_id);
    }

    public function test_the_secret_of_one_stand_never_opens_its_neighbour(): void
    {
        $this->fabriquer(2);
        [$un, $deux] = Stand::orderBy('serial')->get()->all();

        $r = $this->activateur()->activate(
            StandScratch::payload($deux->public_id, $this->secrets[$un->public_id]),
            't-1'
        );

        $this->assertSame(StandActivator::BAD_CODE, $r['result']);
        $this->assertNull($deux->refresh()->tenant_id);
    }

    public function test_a_stand_that_belongs_to_someone_else_stays_theirs(): void
    {
        $this->fabriquer();
        $stand = Stand::first();
        $charge = $this->gratte($stand);

        $this->activateur()->activate($charge, 't-1');

        // Même AVEC le bon secret — un stand volé sur une table est gratté, donc
        // son code est lisible. Une fois réclamé, il ne change plus de mains
        // sans une cession explicite (M6).
        $r = $this->activateur()->activate($charge, 't-2');

        $this->assertSame(StandActivator::NOT_OPEN, $r['result']);
        $this->assertSame('t-1', $stand->refresh()->tenant_id);
    }

    public function test_it_does_not_say_who_owns_a_stand_it_refuses(): void
    {
        // Répondre « déjà à quelqu'un d'autre » transformerait l'écran en
        // annuaire : on saurait, stand par stand, lesquels sont actifs.
        $this->fabriquer();
        $stand = Stand::first();

        $this->activateur()->activate($this->gratte($stand), 't-1');
        $r = $this->activateur()->activate($this->gratte($stand), 't-2');

        $this->assertNull($r['stand'], 'Le stand d\'un autre commerce ne doit pas être renvoyé.');
        $this->assertNull($r['label']);
    }

    public function test_a_lost_stand_cannot_be_activated(): void
    {
        // La seule protection réelle contre le vol d'un objet non gratté : on ne
        // peut pas empêcher qu'on le prenne, on peut faire qu'il ne serve à rien.
        $this->fabriquer();
        $stand = Stand::first();
        $stand->forceFill(['physical_state' => StandState::LOST])->save();

        $r = $this->activateur()->activate($this->gratte($stand), 't-1');

        $this->assertSame(StandActivator::NOT_OPEN, $r['result']);
    }

    public function test_a_recalled_batch_closes_every_stand_at_once(): void
    {
        $this->fabriquer(3);
        Stand::first()->batch->forceFill(['recalled_at' => now()])->save();

        foreach (Stand::all() as $stand) {
            $this->assertSame(StandActivator::NOT_OPEN,
                $this->activateur()->activate($this->gratte($stand), 't-1')['result']);
        }
    }

    /* ------------------------------------------------------------------
       Une seule serrure pour deux portes.
       ------------------------------------------------------------------ */

    public function test_both_paths_share_the_same_attempt_counter(): void
    {
        // Si l'activation en série avait son propre compteur, on forcerait les
        // codes par la porte la moins surveillée. Ici, les essais ratés faits
        // par le parcours public comptent pour celui-ci aussi.
        $this->fabriquer();
        $stand = Stand::first();

        for ($i = 0; $i < StandClaimService::MAX_ATTEMPTS; $i++) {
            app(StandClaimService::class)->verify($stand->public_id, 'ZZZZZZZZ');
        }

        // Le BON code, mais le stand est verrouillé par les essais du VOISIN.
        $r = $this->activateur()->activate($this->gratte($stand), 't-1');

        $this->assertSame(StandActivator::TOO_MANY, $r['result']);
        $this->assertNull($stand->refresh()->tenant_id);
    }

    public function test_a_failed_scan_is_recorded_without_its_secret(): void
    {
        // Une frappe malheureuse peut être le code d'un stand voisin : le
        // journal deviendrait une liste de codes valides en clair.
        $this->fabriquer();
        $stand = Stand::first();

        $this->activateur()->activate($stand->public_id.'-QQQQQQQQ', 't-1');

        $trace = StandClaimAttempt::latest('id')->first();
        $this->assertNotNull($trace);
        $this->assertFalse((bool) $trace->succeeded);

        $json = json_encode($trace->toArray());
        $this->assertStringNotContainsString('QQQQQQQQ', $json);
        $this->assertStringNotContainsString($this->secrets[$stand->public_id], $json);
    }

    public function test_rescanning_your_own_stand_costs_no_attempt(): void
    {
        // On avance dans une salle, la caméra repasse sur une table déjà faite :
        // c'est le cas NORMAL. Rapprocher la limite à chaque fois serait absurde.
        $this->fabriquer();
        $stand = Stand::first();
        $charge = $this->gratte($stand);

        $this->activateur()->activate($charge, 't-1');
        $avant = StandClaimAttempt::count();

        $r = $this->activateur()->activate($charge, 't-1');

        $this->assertSame(StandActivator::ALREADY_MINE, $r['result']);
        $this->assertSame($avant, StandClaimAttempt::count());
    }

    /* ------------------------------------------------------------------
       Les libellés : le second travail répétitif.
       ------------------------------------------------------------------ */

    public function test_places_are_numbered_without_the_merchant_typing(): void
    {
        $this->fabriquer(3);

        $labels = [];
        foreach (Stand::orderBy('serial')->get() as $stand) {
            $labels[] = $this->activateur()->activate($this->gratte($stand), 't-1')['label'];
        }

        $this->assertSame(['Table 1', 'Table 2', 'Table 3'], $labels);
    }

    public function test_a_merchant_can_choose_the_word(): void
    {
        // Un hôtel numérote des chambres, pas des tables.
        $this->fabriquer(2);

        foreach (Stand::orderBy('serial')->get() as $stand) {
            $r = $this->activateur()->activate($this->gratte($stand), 't-1', ['label_prefix' => 'Chambre']);
        }

        $this->assertSame(['Chambre 1', 'Chambre 2'],
            Stand::ofBusiness('t-1')->orderBy('serial')->pluck('location_label')->all());
    }

    public function test_numbering_never_reuses_a_number_already_in_the_room(): void
    {
        // Repartir du COMPTE plutôt que du maximum réattribuerait un numéro déjà
        // porté dès qu'un stand est retiré du service : deux « Table 3 » dans la
        // même salle, et deux QR qui mènent au même endroit sans qu'on sache
        // lequel est lequel.
        $this->fabriquer(4);
        $stands = Stand::orderBy('serial')->get();

        foreach ($stands as $stand) {
            $this->activateur()->activate($this->gratte($stand), 't-1');
        }

        // « Table 2 » sort du service.
        $stands[1]->forceFill(['tenant_id' => null, 'location_label' => null,
            'digital_state' => StandState::UNCLAIMED])->save();

        $this->assertSame('Table 5', $this->activateur()->nextLabel('t-1'));
    }

    public function test_an_explicit_label_wins_over_the_automatic_one(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->activateur()->activate($this->gratte($stand), 't-1',
            ['location_label' => 'Comptoir']);

        $this->assertSame('Comptoir', $r['label']);
    }

    public function test_numbering_is_per_business(): void
    {
        // Le libellé se lit sur les stands DU commerce : sans le cloisonnement,
        // le premier stand d'un nouveau marchand s'appellerait « Table 8 314 ».
        $this->fabriquer(2);
        $stands = Stand::orderBy('serial')->get();

        $this->activateur()->activate($this->gratte($stands[0]), 't-1');
        $r = $this->activateur()->activate($this->gratte($stands[1]), 't-2');

        $this->assertSame('Table 1', $r['label']);
    }

    /* ------------------------------------------------------------------
       Ce qui passe devant une caméra ouverte.
       ------------------------------------------------------------------ */

    public function test_a_random_barcode_is_not_an_error(): void
    {
        // La caméra reste ouverte : elle lit tout ce qui passe — un code
        // produit, une étiquette de transporteur, le dos d'une bouteille.
        foreach (['3017620422003', 'BONJOUR', 'https://exemple.com/x'] as $bruit) {
            $r = $this->activateur()->activate($bruit, 't-1');
            $this->assertSame(StandActivator::UNREADABLE, $r['result']);
        }

        // Et rien n'est écrit dans le journal des tentatives : ce ne sont pas
        // des essais sur un stand, ce sont des objets qui passent.
        $this->assertSame(0, StandClaimAttempt::count());
    }

    public function test_a_stand_that_does_not_exist_says_so(): void
    {
        $this->fabriquer();

        $r = $this->activateur()->activate('TG-999999-A3F9K2MP', 't-1');

        $this->assertSame(StandActivator::NOT_FOUND, $r['result']);
    }

    public function test_without_a_business_nothing_is_activated(): void
    {
        $this->fabriquer();
        $stand = Stand::first();

        $r = $this->activateur()->activate($this->gratte($stand), null);

        $this->assertSame(StandActivator::NO_BUSINESS, $r['result']);
        $this->assertNull($stand->refresh()->tenant_id);
    }

    /* ------------------------------------------------------------------
       L'écran et son point d'entrée.
       ------------------------------------------------------------------ */

    public function test_the_screen_opens(): void
    {
        $this->patron();

        $this->get(route('tagtoa.stand.activate'))
            ->assertOk()
            ->assertSee('Grattez')
            ->assertSee('Table 1');
    }

    public function test_the_endpoint_activates_and_announces_the_next_label(): void
    {
        $this->fabriquer(2);
        $this->patron();
        $stands = Stand::orderBy('serial')->get();

        $this->postJson(route('tagtoa.stand.activate.scan'), [
            'payload' => $this->gratte($stands[0]),
        ])->assertOk()->assertJson([
            'result'     => StandActivator::OK,
            'stand'      => ['public_id' => $stands[0]->public_id, 'label' => 'Table 1'],
            'next_label' => 'Table 2',
        ]);
    }

    public function test_the_endpoint_never_returns_a_secret(): void
    {
        $this->fabriquer();
        $this->patron();
        $stand = Stand::first();

        $corps = $this->postJson(route('tagtoa.stand.activate.scan'), [
            'payload' => $this->gratte($stand),
        ])->assertOk()->getContent();

        $this->assertStringNotContainsString($this->secrets[$stand->public_id], $corps);
        $this->assertStringNotContainsString('secret', $corps);
    }

    public function test_the_endpoint_stays_a_200_whatever_passes_the_camera(): void
    {
        // Un code d'erreur HTTP ferait crier la console du navigateur à chaque
        // étiquette qui passe, et l'écran n'a besoin que d'un verdict lisible.
        $this->patron();

        $this->postJson(route('tagtoa.stand.activate.scan'), ['payload' => 'BONJOUR'])
            ->assertOk()
            ->assertJson(['result' => StandActivator::UNREADABLE]);
    }

    public function test_the_scan_is_recorded_in_the_stand_history(): void
    {
        // En litige, il faut pouvoir distinguer une réclamation publique d'une
        // activation faite en salle par le marchand lui-même.
        $this->fabriquer();
        $this->patron();
        $stand = Stand::first();

        $this->activateur()->activate($this->gratte($stand), 't-1', ['actor_name' => 'Roosevelt']);

        $event = StandEvent::where('stand_id', $stand->id)->where('event', StandEvent::CLAIMED)->first();

        $this->assertNotNull($event);
        $this->assertSame('t-1', $event->tenant_id);
        $this->assertSame('scan', $event->meta['via'] ?? null);
    }

    public function test_the_scan_makes_the_stand_resolve_to_its_menu_right_away(): void
    {
        // Le cache de l'aiguillage garde la destination une heure. Sans l'oubli,
        // un client attablé verrait « non activé » devant un stand qui vient
        // d'être posé sur sa table.
        $this->fabriquer();
        $stand = Stand::first();

        // On remplit le cache avec l'ancienne réponse.
        $this->get('/s/'.$stand->public_id)->assertOk()->assertSee('pas encore activé');

        $this->activateur()->activate($this->gratte($stand), 't-1');

        $this->get('/s/'.$stand->public_id)->assertDontSee('pas encore activé');
    }
}
