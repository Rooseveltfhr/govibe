<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Models\Stand\StandTransfer;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Services\Stand\StandResolver;
use Modules\Tagtoa\App\Services\Stand\StandTransferService;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\App\Support\Stand\TransferCode;
use Modules\Tagtoa\Tests\TestCase;

/**
 * M6 — LA CESSION.
 *
 * Une règle gouverne ce fichier, et tout le reste en découle :
 *
 *   UN STAND ACTIVÉ NE REDEVIENT JAMAIS RÉCLAMABLE.
 *
 * Le panneau à gratter a déjà été gratté : le secret est écrit en clair sur
 * l'objet, et l'ancien propriétaire a pu le photographier. Le rendre vivant
 * donnerait à celui qui vend son bar le pouvoir de reprendre les quarante
 * stands le lendemain et de rediriger les QR posés sur les tables vers son
 * propre menu — le client attablé chez l'acheteur commanderait chez le vendeur.
 *
 * La seule sortie d'un commerce est donc une cession dirigée.
 */
class StandTransferTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    /* ---------------- décor ---------------- */

    private function fabriquer(int $n = 5): void
    {
        $r = app(StandMinter::class)->mint('TAGTOA-2026-006', $n);
        $this->secrets = $r['secrets'];
    }

    /** Fabrique $n stands déjà ACTIFS chez un commerce, comme après activation. */
    private function actifs(string $business, int $n = 3): array
    {
        $this->fabriquer($n);

        $stands = [];
        foreach (Stand::orderBy('serial')->get() as $stand) {
            $r = app(StandActivator::class)->activate(
                StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]),
                $business
            );
            $this->assertSame(StandActivator::OK, $r['result']);
            $stands[] = $stand->refresh();
        }

        return $stands;
    }

    private function service(): StandTransferService
    {
        return app(StandTransferService::class);
    }

    private function connecte(string $business): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $business, 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    private function ids(array $stands): array
    {
        return array_map(fn ($s) => $s->id, $stands);
    }

    /* ==================================================================
       LA RÈGLE : un stand activé ne redevient jamais réclamable.
       ================================================================== */

    public function test_an_activated_stand_never_becomes_claimable_again(): void
    {
        // LE test du module. À aucune étape de la cession — proposée, annulée,
        // acceptée — le secret gratté ne doit reprendre du pouvoir.
        $stands = $this->actifs('biz-a', 2);
        $stand = $stands[0];
        $charge = StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]);

        $this->assertFalse($stand->isClaimable(), 'Activé : plus réclamable.');

        // 1. Pendant la cession.
        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $this->assertSame(StandTransferService::OK, $offre['result']);
        $this->assertFalse($stand->refresh()->isClaimable(), 'En cession : toujours pas réclamable.');

        $r = app(StandActivator::class)->activate($charge, 'biz-voleur');
        $this->assertNotSame(StandActivator::OK, $r['result'],
            'Le secret gratté ne doit JAMAIS reprendre un stand en cession.');
        $this->assertSame('biz-a', $stand->refresh()->tenant_id);

        // 2. Après annulation — le cas qui piège : le stand est redevenu ACTIF.
        $this->service()->cancel($offre['transfer']->id, 'biz-a');
        $this->assertSame(StandState::ACTIVE, $stand->refresh()->digital_state);
        $this->assertFalse($stand->isClaimable(), 'Annulée : le stand reste au cédant.');

        $r = app(StandActivator::class)->activate($charge, 'biz-voleur');
        $this->assertNotSame(StandActivator::OK, $r['result']);
        $this->assertSame('biz-a', $stand->refresh()->tenant_id);

        // 3. Après une cession réellement acceptée : l'ANCIEN propriétaire ne
        //    doit pas pouvoir reprendre l'objet avec le code qu'il a lu.
        $offre2 = $this->service()->offer('biz-a', $this->ids($stands));
        $this->service()->accept($offre2['code'], 'biz-b');
        $this->assertSame('biz-b', $stand->refresh()->tenant_id);

        $r = app(StandActivator::class)->activate($charge, 'biz-a');
        $this->assertNotSame(StandActivator::OK, $r['result'],
            'L\'ancien propriétaire reprendrait son parc après la vente.');
        $this->assertSame('biz-b', $stand->refresh()->tenant_id);
    }

    /* ==================================================================
       Ce qui ne s'éteint pas
       ================================================================== */

    public function test_the_qr_keeps_working_while_the_transfer_is_pending(): void
    {
        // Une vente de fonds de commerce dure des jours. Couper les menus
        // pendant ce temps ferait perdre ces jours de commandes aux deux
        // parties — et le client attablé n'a rien à voir dans la négociation.
        Menu::create(['tenant_id' => 'biz-a', 'name' => 'Chez Lakay', 'alias' => 'lakay',
                      'currency' => 'HTG', 'is_active' => true]);

        $stands = $this->actifs('biz-a', 1);
        $this->service()->offer('biz-a', $this->ids($stands));

        $this->assertSame(StandState::TRANSFER_PENDING, $stands[0]->refresh()->digital_state);

        $d = app(StandResolver::class)->resolve($stands[0]->public_id);

        $this->assertSame(StandResolver::GO_TARGET, $d['go'],
            'Le QR d\'un stand en cession doit continuer de mener au menu.');
        $this->assertStringContainsString('/menu/lakay', (string) $d['url']);
    }

    /* ==================================================================
       Le chemin normal
       ================================================================== */

    public function test_the_stands_change_business_when_the_code_is_presented(): void
    {
        $stands = $this->actifs('biz-a', 3);

        $offre = $this->service()->offer('biz-a', $this->ids($stands), ['note' => 'vente du bar']);
        $this->assertSame(3, $offre['count']);
        $this->assertTrue(TransferCode::isValid($offre['code']));

        $r = $this->service()->accept($offre['code'], 'biz-b');

        $this->assertSame(StandTransferService::OK, $r['result']);
        $this->assertSame(3, $r['count']);

        foreach ($stands as $s) {
            $s->refresh();
            $this->assertSame('biz-b', $s->tenant_id);
            $this->assertSame(StandState::ACTIVE, $s->digital_state);
            $this->assertSame(Stand::HOLDER_BUSINESS, $s->holder_type);
        }

        $this->assertSame('biz-b', $offre['transfer']->refresh()->to_business_id);
        $this->assertNotNull($offre['transfer']->accepted_at);
    }

    public function test_the_giver_loses_access_immediately(): void
    {
        // Le cédant ne doit plus voir NI renommer les stands : il a vendu.
        $stands = $this->actifs('biz-a', 2);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $this->service()->accept($offre['code'], 'biz-b');

        $this->assertSame(0, Stand::ofBusiness('biz-a')->count(),
            'Le cédant voit encore les stands qu\'il a vendus.');
        $this->assertSame(2, Stand::ofBusiness('biz-b')->count());

        // Et il ne peut plus en émettre une seconde cession.
        $encore = $this->service()->offer('biz-a', $this->ids($stands));
        $this->assertSame(StandTransferService::RIEN, $encore['result']);
    }

    public function test_the_first_claim_date_is_not_rewritten_by_a_transfer(): void
    {
        // `claimed_at` prouve l'ancienneté de l'objet dans un litige. La date
        // de la cession vit dans le journal, où elle n'écrase rien.
        $stands = $this->actifs('biz-a', 1);
        $avant = $stands[0]->claimed_at;

        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $this->service()->accept($offre['code'], 'biz-b');

        $this->assertEquals($avant, $stands[0]->refresh()->claimed_at);
    }

    public function test_the_transfer_is_written_in_the_stand_history(): void
    {
        // C'est ce journal qui tranche « je n'ai jamais cédé mes stands ».
        $stands = $this->actifs('biz-a', 1);
        $offre = $this->service()->offer('biz-a', $this->ids($stands), ['actor_name' => 'Roosevelt']);
        $this->service()->accept($offre['code'], 'biz-b');

        $e = StandEvent::where('stand_id', $stands[0]->id)
            ->where('event', StandEvent::TRANSFER)->first();

        $this->assertNotNull($e, 'La cession doit laisser une trace.');
        $this->assertSame('biz-a', $e->meta['from']);
        $this->assertSame('biz-b', $e->meta['to']);
    }

    /* ==================================================================
       Le cache du résolveur
       ================================================================== */

    public function test_the_scan_destination_cache_is_cleared_on_acceptance(): void
    {
        // Sans cela, l'ancienne destination serait servie une heure durant :
        // le menu de l'ancien propriétaire au client du nouveau, sur des
        // tables déjà vendues.
        Menu::create(['tenant_id' => 'biz-a', 'name' => 'Chez Lakay', 'alias' => 'lakay',
                      'currency' => 'HTG', 'is_active' => true]);
        Menu::create(['tenant_id' => 'biz-b', 'name' => 'Chez Jean', 'alias' => 'jean',
                      'currency' => 'HTG', 'is_active' => true]);

        $stands = $this->actifs('biz-a', 1);

        // On réchauffe le cache comme le ferait un vrai client attablé.
        $this->assertStringContainsString('/menu/lakay',
            (string) app(StandResolver::class)->resolve($stands[0]->public_id)['url']);

        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $this->service()->accept($offre['code'], 'biz-b');

        $this->assertNull(Cache::get('tagtoa:stand:'.$stands[0]->public_id),
            'Le cache de destination doit être vidé par la cession.');

        $this->assertStringContainsString('/menu/jean',
            (string) app(StandResolver::class)->resolve($stands[0]->public_id)['url'],
            'Le scan doit mener au menu du REPRENEUR.');
    }

    /* ==================================================================
       Le code : usage unique, durée limitée, jamais en clair
       ================================================================== */

    public function test_a_code_only_works_once(): void
    {
        $stands = $this->actifs('biz-a', 2);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));

        $this->assertSame(StandTransferService::OK,
            $this->service()->accept($offre['code'], 'biz-b')['result']);

        // Le même code, transmis à un troisième : il ne reprend rien.
        $r = $this->service()->accept($offre['code'], 'biz-c');

        $this->assertSame(StandTransferService::DEJA_UTILISE, $r['result']);
        $this->assertSame('biz-b', $stands[0]->refresh()->tenant_id);
    }

    public function test_the_plain_code_never_enters_the_database(): void
    {
        $stands = $this->actifs('biz-a', 1);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $clair = TransferCode::normalize($offre['code']);

        $ligne = (array) StandTransfer::find($offre['transfer']->id)->getAttributes();

        foreach ($ligne as $colonne => $valeur) {
            $this->assertStringNotContainsString($clair, (string) $valeur,
                "Le code en clair apparaît dans la colonne « $colonne ».");
        }

        $this->assertSame(TransferCode::fingerprint($clair), $ligne['code_hash']);
    }

    public function test_an_expired_offer_gives_the_stands_back_and_is_refused(): void
    {
        $stands = $this->actifs('biz-a', 2);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));

        // Le temps passe : le code traîne sur WhatsApp trois jours plus tard.
        $offre['transfer']->forceFill(['expires_at' => now()->subMinute()])->save();

        $r = $this->service()->accept($offre['code'], 'biz-b');

        $this->assertSame(StandTransferService::EXPIREE, $r['result']);
        $this->assertSame('biz-a', $stands[0]->refresh()->tenant_id);
        $this->assertSame(StandState::ACTIVE, $stands[0]->digital_state,
            'Une offre périmée ne doit pas laisser le parc bloqué en cession.');
    }

    public function test_expired_offers_are_swept_when_the_screen_opens(): void
    {
        $stands = $this->actifs('biz-a', 3);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $offre['transfer']->forceFill(['expires_at' => now()->subHour()])->save();

        $n = $this->service()->expireStale('biz-a');

        $this->assertSame(3, $n);
        foreach ($stands as $s) {
            $this->assertSame(StandState::ACTIVE, $s->refresh()->digital_state);
        }
    }

    /* ==================================================================
       Ce qu'on ne peut pas faire
       ================================================================== */

    public function test_you_cannot_offer_someone_elses_stands(): void
    {
        // Le modèle Stand n'a pas de portée automatique : sans le filtre
        // explicite, envoyer les identifiants d'un concurrent suffirait.
        $stands = $this->actifs('biz-a', 2);

        $r = $this->service()->offer('biz-voleur', $this->ids($stands));

        $this->assertSame(StandTransferService::RIEN, $r['result']);
        $this->assertSame(StandState::ACTIVE, $stands[0]->refresh()->digital_state);
    }

    public function test_a_stand_cannot_be_in_two_offers_at_once(): void
    {
        // Sinon deux codes circulent pour le même objet, et le second
        // repreneur découvre qu'il a acheté un stand déjà parti.
        $stands = $this->actifs('biz-a', 2);

        $this->assertSame(StandTransferService::OK,
            $this->service()->offer('biz-a', $this->ids($stands))['result']);

        $second = $this->service()->offer('biz-a', $this->ids($stands));
        $this->assertSame(StandTransferService::RIEN, $second['result']);
    }

    public function test_you_cannot_transfer_to_yourself(): void
    {
        // Consommerait le code sans rien déplacer, en laissant croire que la
        // cession est faite.
        $stands = $this->actifs('biz-a', 1);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));

        $r = $this->service()->accept($offre['code'], 'biz-a');

        $this->assertSame(StandTransferService::SOI_MEME, $r['result']);
        $this->assertNull($offre['transfer']->refresh()->accepted_at, 'Le code ne doit pas être consommé.');
    }

    public function test_cancelling_after_acceptance_is_refused(): void
    {
        // Reprendre les stands après la vente EST le vol que ce module doit
        // empêcher : ce serait rediriger les QR du bar qu'on vient de vendre.
        $stands = $this->actifs('biz-a', 2);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));
        $this->service()->accept($offre['code'], 'biz-b');

        $r = $this->service()->cancel($offre['transfer']->id, 'biz-a');

        $this->assertSame(StandTransferService::DEJA_UTILISE, $r['result']);
        $this->assertSame('biz-b', $stands[0]->refresh()->tenant_id);
    }

    public function test_another_merchant_cannot_cancel_your_offer(): void
    {
        $stands = $this->actifs('biz-a', 1);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));

        $r = $this->service()->cancel($offre['transfer']->id, 'biz-voleur');

        $this->assertSame(StandTransferService::PAS_A_LUI, $r['result']);
        $this->assertSame(StandState::TRANSFER_PENDING, $stands[0]->refresh()->digital_state);
    }

    public function test_cancelling_gives_the_stands_back(): void
    {
        $stands = $this->actifs('biz-a', 3);
        $offre = $this->service()->offer('biz-a', $this->ids($stands));

        $r = $this->service()->cancel($offre['transfer']->id, 'biz-a');

        $this->assertSame(StandTransferService::OK, $r['result']);
        $this->assertSame(3, $r['count']);
        foreach ($stands as $s) {
            $this->assertSame(StandState::ACTIVE, $s->refresh()->digital_state);
            $this->assertSame('biz-a', $s->tenant_id);
        }

        // Et le code cesse de fonctionner.
        $this->assertSame(StandTransferService::ANNULEE,
            $this->service()->accept($offre['code'], 'biz-b')['result']);
    }

    public function test_an_unknown_code_takes_nothing(): void
    {
        $this->actifs('biz-a', 1);

        $this->assertSame(StandTransferService::INTROUVABLE,
            $this->service()->accept('ZZZZZZZZZZZZ', 'biz-b')['result']);
        $this->assertSame(StandTransferService::INTROUVABLE,
            $this->service()->accept('trop-court', 'biz-b')['result']);
    }

    public function test_a_huge_selection_is_refused_rather_than_truncated(): void
    {
        // Un nombre sans plafond permettrait de bloquer d'un coup tout le parc
        // d'un commerce sur une seule requête.
        $stands = $this->actifs('biz-a', 1);
        $gonflee = array_merge($this->ids($stands), range(10000, 10000 + StandTransferService::MAX_STANDS));

        $r = $this->service()->offer('biz-a', $gonflee);

        $this->assertSame(StandTransferService::TROP, $r['result']);
        $this->assertSame(StandState::ACTIVE, $stands[0]->refresh()->digital_state);
    }

    /* ==================================================================
       Les écrans
       ================================================================== */

    public function test_the_screen_shows_the_code_once_and_never_again(): void
    {
        $stands = $this->actifs('biz-a', 2);
        $this->connecte('biz-a');

        $this->post(route('tagtoa.stand.transfer.store'), [
            'stands' => $this->ids($stands), 'note' => 'vente du bar',
        ])->assertRedirect(route('tagtoa.stand.transfer.index'));

        $clair = TransferCode::normalize(session('tagtoa_transfer_code'));
        $this->assertTrue(TransferCode::isValid($clair));

        // Premier passage : le code est là.
        $this->assertStringContainsString(TransferCode::pretty($clair),
            $this->get(route('tagtoa.stand.transfer.index'))->assertOk()->getContent());

        // Second : il a disparu, et rien ne permet de le retrouver.
        $html = $this->get(route('tagtoa.stand.transfer.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString($clair, $html);
        $this->assertStringNotContainsString(TransferCode::pretty($clair), $html);
        $this->assertStringNotContainsString(TransferCode::fingerprint($clair), $html,
            'Même l\'empreinte ne doit pas sortir : elle permettrait de tester des codes hors ligne.');
    }

    public function test_the_accept_screen_names_the_business_that_will_receive(): void
    {
        // Un compte peut tenir plusieurs commerces. Reprendre quarante stands
        // dans la boulangerie au lieu du bar est une erreur silencieuse.
        \Modules\Tagtoa\App\Models\Business\Business::create([
            'id' => 'biz-b', 'account_id' => 'biz-b', 'name' => 'Bar Jean-Claude',
            'type' => 'bar', 'currency' => 'HTG', 'is_active' => true,
        ]);
        $this->connecte('biz-b');

        $this->get(route('tagtoa.stand.transfer.accept.form'))
            ->assertOk()
            ->assertSee('Bar Jean-Claude');
    }

    public function test_the_whole_journey_through_the_screens(): void
    {
        $stands = $this->actifs('biz-a', 2);

        $this->connecte('biz-a');
        $this->post(route('tagtoa.stand.transfer.store'), ['stands' => $this->ids($stands)]);
        $code = session('tagtoa_transfer_code');

        $this->connecte('biz-b');
        $this->post(route('tagtoa.stand.transfer.accept'), ['code' => $code])
            ->assertRedirect(route('tagtoa.stand.index'));

        $this->assertSame('biz-b', $stands[0]->refresh()->tenant_id);
        $this->assertSame('biz-b', $stands[1]->refresh()->tenant_id);
    }

    public function test_the_giver_never_sees_another_merchants_transfers(): void
    {
        // Qui vend son affaire, et à qui : ce n'est l'affaire de personne
        // d'autre. Le modèle n'a pas de portée automatique.
        $a = $this->actifs('biz-a', 1);
        $this->service()->offer('biz-a', $this->ids($a), ['note' => 'vente du bar de Wilner']);

        $this->connecte('biz-b');
        $html = $this->get(route('tagtoa.stand.transfer.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('vente du bar de Wilner', $html);
        $this->assertStringNotContainsString($a[0]->public_id, $html);
    }
}
