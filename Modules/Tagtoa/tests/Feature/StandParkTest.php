<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Services\Stand\ResellerService;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Services\Stand\StandAdminService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Services\Stand\StandResolver;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\Tests\TestCase;

/**
 * M7 — LE PARC, VU ET TENU PAR LE FONDATEUR.
 *
 * Trois choses que rien ne savait dire, et une qu'il fallait encadrer :
 *
 *   1. COMBIEN DE STANDS SONT MUETS — vendus, et jamais activés. Le marchand a
 *      payé et n'a jamais eu son compte. C'est la seule fuite qui ne produit
 *      aucune erreur, aucun ticket, aucune réclamation : le marchand croit que
 *      « le truc ne marche pas » et le range dans un tiroir.
 *   2. OÙ EST PASSÉ LE STAND 57, et qui l'a touché.
 *   3. DÉCLARER PERDU RÉVOQUE LE CODE — la seule protection réelle contre un
 *      stand volé.
 *   4. LA CESSION FORCÉE, qui ne s'exerce jamais en silence.
 */
class StandParkTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function fondateur(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 'tagtoa', 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    private function fabriquer(int $n = 10): void
    {
        $r = app(StandMinter::class)->mint('TAGTOA-2026-007', $n);
        $this->secrets = $r['secrets'];
    }

    private function service(): StandAdminService
    {
        return app(StandAdminService::class);
    }

    /** Active un stand chez un commerce, par le vrai chemin. */
    private function activer(Stand $stand, string $business): void
    {
        $r = app(StandActivator::class)->activate(
            StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]), $business
        );
        $this->assertSame(StandActivator::OK, $r['result']);
    }

    /* ==================================================================
       1. LES STANDS MUETS — le chiffre du module
       ================================================================== */

    public function test_it_counts_the_stands_that_were_sold_and_never_activated(): void
    {
        // Le revendeur a encaissé, le marchand a payé, et aucun compte
        // n'existe derrière. Rien d'autre dans la plateforme ne dit ce chiffre.
        $this->fabriquer(6);
        $rev = Reseller::create(['business_id' => 'biz-rev', 'name' => 'Wilner', 'is_active' => true]);
        $lot = (int) Stand::first()->batch_id;
        app(ResellerService::class)->allocate($rev, $lot, 1, 6);

        // Trois vendus…
        foreach (Stand::orderBy('serial')->take(3)->get() as $s) {
            app(ResellerService::class)->declareSale($rev, $s->public_id);
        }
        // …dont un seul activé par son marchand.
        $this->activer(Stand::orderBy('serial')->first(), 'biz-a');

        $parc = $this->service()->parc();

        $this->assertSame(2, $parc['muets'], 'Deux objets payés sans compte derrière.');
        $this->assertSame(1, $parc['actifs']);
        $this->assertSame(6, $parc['total']);
    }

    public function test_a_stand_still_in_the_warehouse_is_not_mute(): void
    {
        // Un stand jamais vendu n'est pas une fuite : c'est du stock. Les
        // confondre gonflerait le chiffre au point de le rendre inutile.
        $this->fabriquer(5);

        $this->assertSame(0, $this->service()->parc()['muets']);
    }

    public function test_it_counts_the_stands_activated_but_never_scanned(): void
    {
        // Un autre échec, plus tardif : le compte existe, l'objet est dans un
        // tiroir plutôt que sur une table.
        $this->fabriquer(3);
        $this->activer(Stand::orderBy('serial')->first(), 'biz-a');

        $this->assertSame(1, $this->service()->parc()['jamais_scannes']);
    }

    public function test_the_park_can_be_read_one_batch_at_a_time(): void
    {
        $this->fabriquer(4);
        $lot = (int) Stand::first()->batch_id;

        $this->assertSame(4, $this->service()->parc($lot)['total']);
        $this->assertSame(0, $this->service()->parc($lot + 999)['total']);
    }

    /* ==================================================================
       2. L'HISTOIRE D'UN STAND
       ================================================================== */

    public function test_the_history_shows_every_step_the_object_went_through(): void
    {
        // C'est ce qui tranche « ce stand est à moi ».
        $this->fabriquer(3);
        $rev = Reseller::create(['business_id' => 'biz-rev', 'name' => 'Wilner', 'is_active' => true]);
        $lot = (int) Stand::first()->batch_id;
        app(ResellerService::class)->allocate($rev, $lot, 1, 3);

        $stand = Stand::orderBy('serial')->first();
        app(ResellerService::class)->declareSale($rev, $stand->public_id);
        $this->activer($stand->refresh(), 'biz-a');

        $evenements = $this->service()->history($stand)->pluck('event')->all();

        foreach ([StandEvent::MINTED, StandEvent::ALLOCATED, StandEvent::SOLD, StandEvent::CLAIMED] as $attendu) {
            $this->assertContains($attendu, $evenements, "L'événement « $attendu » manque à l'histoire.");
        }
    }

    /* ==================================================================
       3. LES ACTES DU FONDATEUR
       ================================================================== */

    public function test_declaring_a_stand_lost_revokes_its_code(): void
    {
        // LE test de la section. On ne peut pas empêcher qu'on prenne l'objet ;
        // on peut faire qu'il ne serve à rien.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $charge = StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]);

        $this->assertTrue($stand->isClaimable());

        $r = $this->service()->markPhysical($stand->public_id, StandState::LOST, ['motif' => 'volé en transit']);
        $this->assertSame(StandAdminService::OK, $r['result']);

        $this->assertFalse($stand->refresh()->isClaimable());

        $a = app(StandActivator::class)->activate($charge, 'biz-voleur');
        $this->assertNotSame(StandActivator::OK, $a['result'],
            'Un stand déclaré perdu doit refuser son propre code.');
        $this->assertNull($stand->refresh()->tenant_id);
    }

    public function test_declaring_a_stand_lost_keeps_the_holder_on_record(): void
    {
        // C'est le revendeur qu'on rappelle : « le carton 41-80 est chez
        // Wilner, et le 57 n'est jamais arrivé ». L'effacer perdrait
        // exactement l'information qui sert à retrouver l'objet.
        $this->fabriquer(3);
        $rev = Reseller::create(['business_id' => 'biz-rev', 'name' => 'Wilner', 'is_active' => true]);
        app(ResellerService::class)->allocate($rev, (int) Stand::first()->batch_id, 1, 3);

        $stand = Stand::orderBy('serial')->first();
        $this->service()->markPhysical($stand->public_id, StandState::LOST);

        $stand->refresh();
        $this->assertSame(Stand::HOLDER_RESELLER, $stand->holder_type);
        $this->assertSame($rev->id, (int) $stand->holder_id);
    }

    public function test_a_stand_claimed_by_a_shop_cannot_be_put_back_in_the_warehouse(): void
    {
        // Ce serait dire qu'il est chez nous alors qu'il est sur la table de
        // quelqu'un. L'identité passe par une cession, jamais par l'axe
        // physique — c'est toute la raison des deux axes.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        $r = $this->service()->markPhysical($stand->public_id, StandState::IN_STOCK);

        $this->assertSame(StandAdminService::IMPOSSIBLE, $r['result']);
        $this->assertSame('biz-a', $stand->refresh()->tenant_id);
    }

    public function test_a_stand_returned_to_the_warehouse_leaves_its_reseller(): void
    {
        $this->fabriquer(3);
        $rev = Reseller::create(['business_id' => 'biz-rev', 'name' => 'Wilner', 'is_active' => true]);
        app(ResellerService::class)->allocate($rev, (int) Stand::first()->batch_id, 1, 3);

        $stand = Stand::orderBy('serial')->first();
        $this->service()->markPhysical($stand->public_id, StandState::IN_STOCK);

        $stand->refresh();
        $this->assertSame(StandState::IN_STOCK, $stand->physical_state);
        $this->assertSame(Stand::HOLDER_PLATFORM, $stand->holder_type);
        $this->assertNull($stand->holder_id);
    }

    public function test_suspending_shows_a_sober_page_never_an_error(): void
    {
        // Le stand est posé sur une table, devant un client qui a faim. On
        // prévient le commerçant, pas son client.
        Menu::create(['tenant_id' => 'biz-a', 'name' => 'Lakay', 'alias' => 'lakay',
                      'currency' => 'HTG', 'is_active' => true]);
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        $this->service()->markDigital($stand->public_id, StandState::SUSPENDED);

        $d = app(StandResolver::class)->resolve($stand->public_id);
        $this->assertSame(StandResolver::GO_PAUSED, $d['go']);
    }

    public function test_an_act_clears_the_scan_destination_cache(): void
    {
        // Sans cela, l'ancienne page continuerait d'être servie une heure
        // durant — c'est-à-dire un menu vivant pour un stand qu'on vient de
        // suspendre.
        Menu::create(['tenant_id' => 'biz-a', 'name' => 'Lakay', 'alias' => 'lakay',
                      'currency' => 'HTG', 'is_active' => true]);
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        app(StandResolver::class)->resolve($stand->public_id);   // on réchauffe
        $this->assertNotNull(Cache::get('tagtoa:stand:'.$stand->public_id));

        $this->service()->markDigital($stand->public_id, StandState::SUSPENDED);

        $this->assertNull(Cache::get('tagtoa:stand:'.$stand->public_id),
            'Le cache de destination doit être vidé par un acte du fondateur.');
    }

    public function test_a_stand_with_no_shop_cannot_be_switched_on(): void
    {
        // Poser « actif » sur un stand sans propriétaire fabriquerait une
        // identité qui ne mène nulle part.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();

        $r = $this->service()->markDigital($stand->public_id, StandState::ACTIVE);

        $this->assertSame(StandAdminService::IMPOSSIBLE, $r['result']);
        $this->assertSame(StandState::UNCLAIMED, $stand->refresh()->digital_state);
    }

    public function test_the_founder_cannot_hand_write_a_journey_state(): void
    {
        // GARDE. `unclaimed`, `claim_pending` et `transfer_pending` sont des
        // états de PARCOURS. Remettre un stand en « non réclamé » rendrait
        // vivant un secret DÉJÀ GRATTÉ — donc déjà lu par son ancien
        // propriétaire, qui reprendrait le parc qu'il a vendu.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        foreach ([StandState::UNCLAIMED, StandState::CLAIM_PENDING, StandState::TRANSFER_PENDING] as $etat) {
            $r = $this->service()->markDigital($stand->public_id, $etat);
            $this->assertSame(StandAdminService::IMPOSSIBLE, $r['result'],
                "L'état de parcours « $etat » ne doit pas s'écrire à la main.");
        }

        $this->assertSame(StandState::ACTIVE, $stand->refresh()->digital_state);
        $this->assertFalse($stand->isClaimable());
    }

    /* ==================================================================
       4. LA CESSION FORCÉE
       ================================================================== */

    public function test_a_forced_transfer_without_a_written_reason_is_refused(): void
    {
        // LE garde-fou du pouvoir le plus dangereux de la plateforme. Rien
        // dans le code ne peut empêcher le fondateur d'en abuser ; ce qui peut
        // être fait, c'est le rendre impossible à nier.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        foreach (['', '   ', 'perdu', 'erreur'] as $mauvais) {
            $r = $this->service()->forceTransfer($stand->public_id, 'biz-b', $mauvais);
            $this->assertSame(StandAdminService::SANS_MOTIF, $r['result'],
                "Le motif « $mauvais » n'explique rien et doit être refusé.");
        }

        $this->assertSame('biz-a', $stand->refresh()->tenant_id);
    }

    public function test_a_forced_transfer_writes_both_shops_and_the_reason(): void
    {
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        $r = $this->service()->forceTransfer($stand->public_id, 'biz-b',
            'propriétaire décédé, repris par sa fille — acte du 12/09',
            ['actor_name' => 'Roosevelt', 'ip' => '10.0.0.1']);

        $this->assertSame(StandAdminService::OK, $r['result']);
        $this->assertSame('biz-b', $stand->refresh()->tenant_id);
        $this->assertSame(StandState::ACTIVE, $stand->digital_state);

        $e = StandEvent::where('stand_id', $stand->id)->where('event', StandEvent::TRANSFER)->latest('id')->first();

        $this->assertNotNull($e);
        $this->assertTrue($e->meta['force']);
        $this->assertSame('biz-a', $e->meta['from']);
        $this->assertSame('biz-b', $e->meta['to']);
        $this->assertStringContainsString('décédé', $e->meta['motif']);
        $this->assertSame('Roosevelt', $e->actor_name);
    }

    public function test_a_forced_transfer_consumes_any_pending_reservation(): void
    {
        // Sans cela, un jeton encore valable permettrait de réclamer le stand
        // par-dessus la décision du fondateur.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');
        $stand->forceFill(['claim_reserved_until' => now()->addHour(),
                           'claim_reserved_token' => hash('sha256', 'x')])->save();

        $this->service()->forceTransfer($stand->public_id, 'biz-b', 'compte mort depuis un an');

        $stand->refresh();
        $this->assertNull($stand->claim_reserved_until);
        $this->assertNull($stand->claim_reserved_token);
    }

    public function test_an_object_out_of_circulation_is_not_forced_anywhere(): void
    {
        // Un stand perdu rattaché à un commerce lui promettrait un service qui
        // n'arrivera jamais.
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');
        $this->service()->markPhysical($stand->public_id, StandState::LOST);

        $r = $this->service()->forceTransfer($stand->public_id, 'biz-b', 'repreneur du local depuis mars');

        $this->assertSame(StandAdminService::IMPOSSIBLE, $r['result']);
        $this->assertSame('biz-a', $stand->refresh()->tenant_id);
    }

    public function test_a_forced_transfer_clears_the_scan_destination_cache(): void
    {
        Menu::create(['tenant_id' => 'biz-a', 'name' => 'Lakay', 'alias' => 'lakay',
                      'currency' => 'HTG', 'is_active' => true]);
        Menu::create(['tenant_id' => 'biz-b', 'name' => 'Jean', 'alias' => 'jean',
                      'currency' => 'HTG', 'is_active' => true]);
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');

        app(StandResolver::class)->resolve($stand->public_id);

        $this->service()->forceTransfer($stand->public_id, 'biz-b', 'commerce fermé sans prévenir');

        $this->assertStringContainsString('/menu/jean',
            (string) app(StandResolver::class)->resolve($stand->public_id)['url'],
            'Le scan doit mener au menu du nouveau commerce.');
    }

    /* ==================================================================
       LES ÉCRANS
       ================================================================== */

    public function test_the_console_never_renders_a_secret(): void
    {
        // Même exigence qu'à la console du revendeur : un code lisible dans un
        // navigateur resté ouvert sur un comptoir est un code perdu.
        $this->fabriquer(4);
        $stand = Stand::orderBy('serial')->first();
        $this->fondateur();

        $pages = [
            $this->get(route('tagtoa.superadmin.stands'))->assertOk()->getContent(),
            $this->get(route('tagtoa.superadmin.stand', $stand->public_id))->assertOk()->getContent(),
        ];

        foreach ($pages as $html) {
            foreach (Stand::all() as $s) {
                $this->assertStringNotContainsString($this->secrets[$s->public_id], $html,
                    "Le code de {$s->public_id} apparaît dans la console du fondateur.");
                $this->assertStringNotContainsString((string) $s->secret_hash, $html);
            }
        }
    }

    public function test_the_park_screen_shows_the_mute_count_first(): void
    {
        $this->fabriquer(4);
        $rev = Reseller::create(['business_id' => 'biz-rev', 'name' => 'Wilner', 'is_active' => true]);
        app(ResellerService::class)->allocate($rev, (int) Stand::first()->batch_id, 1, 4);
        foreach (Stand::orderBy('serial')->take(2)->get() as $s) {
            app(ResellerService::class)->declareSale($rev, $s->public_id);
        }

        $this->fondateur();
        $html = $this->get(route('tagtoa.superadmin.stands'))->assertOk()->getContent();

        $this->assertStringContainsString('VENDUS ET MUETS', $html);
        $this->assertStringContainsString('payés, jamais activés', $html);
    }

    public function test_the_whole_console_sits_behind_the_founder_role(): void
    {
        // Le harnais remplace `role` par un passe-plat : les tests ci-dessus
        // passeraient même si le parc était grand ouvert. On vérifie donc la
        // DÉCLARATION, faute de pouvoir vérifier l'exécution.
        $routes = (string) file_get_contents(__DIR__.'/../../routes/web.php');
        $bloc = strstr($routes, 'role:super_admin');

        $this->assertNotFalse($bloc);
        foreach (['superadmin.stands', 'superadmin.stand.physical',
                  'superadmin.stand.digital', 'superadmin.stand.force'] as $nom) {
            $this->assertStringContainsString($nom, $bloc, "« $nom » hors du groupe super-admin.");
        }
    }

    public function test_the_screen_refuses_a_forced_transfer_with_a_short_reason(): void
    {
        $this->fabriquer(2);
        $stand = Stand::orderBy('serial')->first();
        $this->activer($stand, 'biz-a');
        $this->fondateur();

        $this->put(route('tagtoa.superadmin.stand.force', $stand->public_id), [
            'to_business_id' => 'biz-b', 'motif' => 'bof',
        ])->assertSessionHasErrors('motif');

        $this->assertSame('biz-a', $stand->refresh()->tenant_id);
    }
}
