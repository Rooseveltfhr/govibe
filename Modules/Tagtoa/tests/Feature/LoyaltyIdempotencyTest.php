<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA LOYALTY — idempotence des mouvements (topUp/earnPoints/redeem)
|--------------------------------------------------------------------------
| Diagnostic de session (audit sécurité LOYALTY) : Card::lockForUpdate()
| empêche une PERTE d'écriture entre deux appels concurrents, mais ne les
| rend pas idempotents — un deuxième appel pour la MÊME référence, une fois
| le premier verrou relâché, créditait/débitait une deuxième fois. Cas réel :
| une commande MENU payée confirmée à la fois par /pay/{gateway}/return
| (public, sans throttle) et par le webhook de la passerelle, qui atterrissent
| tous les deux sur earnPoints() avec la même Order::reference.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Loyalty\Transaction;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\Tests\TestCase;

class LoyaltyIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function program(): Program
    {
        return Program::create([
            'name' => 'Fidélité', 'alias' => 'fidelite-idem-'.uniqid(),
            'points_per_dollar' => 1, 'dollar_per_point' => 0.01, 'currency' => 'HTG',
        ]);
    }

    public function test_earning_points_twice_with_the_same_reference_credits_only_once(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        $svc->earnPoints($card, 30, ['reference' => 'menu-order-REF1']);
        $svc->earnPoints($card, 30, ['reference' => 'menu-order-REF1']); // webhook + retour navigateur

        $card->refresh();
        $this->assertEquals(30, $card->points);
        $this->assertSame(1, Transaction::where('card_id', $card->id)->where('reference', 'menu-order-REF1')->count());
    }

    public function test_topping_up_twice_with_the_same_reference_credits_only_once(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        $svc->topUp($card, 500, ['reference' => 'topup-REF1']);
        $svc->topUp($card, 500, ['reference' => 'topup-REF1']);

        $card->refresh();
        $this->assertEquals(500, $card->balance);
        $this->assertSame(1, Transaction::where('card_id', $card->id)->where('reference', 'topup-REF1')->count());
    }

    public function test_redeeming_twice_with_the_same_reference_debits_only_once(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $svc->topUp($card, 500);

        $svc->redeem($card, 120, ['reference' => 'redeem-REF1']);
        $svc->redeem($card, 120, ['reference' => 'redeem-REF1']);

        $card->refresh();
        $this->assertEquals(380, $card->balance);
        $this->assertSame(1, Transaction::where('card_id', $card->id)->where('reference', 'redeem-REF1')->count());
    }

    /** Sans référence, chaque appel reste un mouvement distinct — pas de faux dédoublonnage. */
    public function test_earning_points_without_a_reference_is_never_deduplicated(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        $svc->earnPoints($card, 10);
        $svc->earnPoints($card, 10);

        $card->refresh();
        $this->assertEquals(20, $card->points);
        $this->assertSame(2, Transaction::where('card_id', $card->id)->whereNull('reference')->count());
    }

    /**
     * Une erreur de base de données ne doit JAMAIS atterrir dans le bandeau
     * d'erreur du marchand. En PHP, QueryException hérite de PDOException,
     * qui hérite de RuntimeException : un `catch (\RuntimeException)` posé
     * pour afficher « Solde insuffisant » attrapait donc aussi la panne SQL,
     * et en affichait le message — la requête complète avec ses valeurs. Et
     * une écriture réellement échouée passait pour une saisie invalide.
     */
    public function test_a_database_error_is_never_shown_as_a_validation_error(): void
    {
        $card = app(LoyaltyCardService::class)
            ->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => null]));

        $svc = \Mockery::mock(LoyaltyCardService::class);
        $svc->shouldReceive('topUp')->andThrow(new QueryException(
            'sqlite',
            'insert into "tagtoa_loyalty_transactions" ("amount") values (?)',
            [999999999],
            new \Exception('SQLSTATE[22003]: Numeric value out of range')
        ));
        $this->app->instance(LoyaltyCardService::class, $svc);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        // Montant volontairement valide : c'est le service substitué qui
        // lève la panne, pas la validation (voir le test de la borne).
        $this->post(route('tagtoa.loyalty.dashboard.cards.topup', $card->id), ['amount' => 100]);
    }

    /** La règle métier, elle, reste bien affichée au marchand. */
    public function test_a_business_rule_is_still_shown_to_the_merchant(): void
    {
        $card = app(LoyaltyCardService::class)
            ->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $card->update(['status' => Card::STATUS_SUSPENDED]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => null]));

        $this->post(route('tagtoa.loyalty.dashboard.cards.topup', $card->id), ['amount' => 100])
            ->assertSessionHasErrors('amount');
    }


    /**
     * Le piège que la portée (card_id, reference) laissait ouvert : une même
     * référence de commande porte DEUX mouvements légitimes et opposés — les
     * points gagnés à l'achat, puis repris au remboursement. Avec l'ancienne
     * portée, le remboursement retrouvait la ligne du GAIN, la prenait pour un
     * rejeu, et renvoyait « déjà fait » SANS débiter : argent rendu, points
     * gardés, écran vert.
     */
    public function test_a_refund_can_reuse_the_order_reference_of_the_earn_it_reverses(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $svc->topUp($card, 500);

        $svc->earnPoints($card, 40, ['reference' => 'CMD-77']);
        $this->assertEquals(540, $card->fresh()->points);

        // Le remboursement de LA MÊME commande doit vraiment débiter.
        $svc->redeem($card, 0, ['points_delta' => -40, 'reference' => 'CMD-77']);

        $this->assertEquals(500, $card->fresh()->points, 'le remboursement doit reprendre les points, pas se croire déjà fait.');
        $this->assertSame(2, Transaction::where('card_id', $card->id)->where('reference', 'CMD-77')->count());
    }

    /** Chaque type reste idempotent pour lui-même. */
    public function test_each_movement_type_stays_idempotent_on_its_own(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        $svc->earnPoints($card, 25, ['reference' => 'CMD-88']);
        $svc->earnPoints($card, 25, ['reference' => 'CMD-88']);

        $this->assertEquals(25, $card->fresh()->points);
        $this->assertSame(1, Transaction::where('card_id', $card->id)->where('type', Transaction::TYPE_EARN)->count());
    }

    public function test_an_amount_above_the_form_ceiling_is_refused_by_validation(): void
    {
        // 99 999 999,50 tient dans decimal(10,2) : le SERVICE l'accepterait sur
        // une carte vide. Seule la validation du formulaire (max:99999999) le
        // refuse — c'est ce qui distingue ce test de celui du débordement, où
        // la garde du service répond à la place du formulaire.
        $card = app(LoyaltyCardService::class)
            ->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => null]));

        $this->post(route('tagtoa.loyalty.dashboard.cards.topup', $card->id), ['amount' => 99999999.5])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Transaction::where('card_id', $card->id)->count());
    }

    /**
     * `max` sur le formulaire borne le MONTANT, pas le solde qui en résulte :
     * 99 999 999 ajoutés à une carte déjà créditée dépassent decimal(10,2).
     * Avant, l'écriture échouait en base ; c'est maintenant une règle métier
     * lisible, et rien n'est crédité.
     */
    public function test_a_topup_that_would_overflow_the_balance_column_is_refused(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $svc->topUp($card, 500);

        $this->expectException(\Modules\Tagtoa\App\Exceptions\LoyaltyRuleException::class);

        try {
            $svc->topUp($card, 99999999);
        } finally {
            $this->assertEquals(500, $card->fresh()->balance, 'aucun crédit ne doit passer.');
            $this->assertSame(1, Transaction::where('card_id', $card->id)->count());
        }
    }

    public function test_a_topup_right_up_to_the_ceiling_still_works(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        $svc->topUp($card, 99999999);

        $this->assertEquals(99999999, $card->fresh()->balance);
    }

    public function test_a_suspended_card_cannot_be_topped_up(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];
        $card->update(['status' => Card::STATUS_SUSPENDED]);

        $this->expectException(\RuntimeException::class);
        $svc->topUp($card, 100);
    }

    /**
     * La vraie fenêtre de course sur le chemin PUBLIC : le webhook de la
     * passerelle et le retour navigateur confirment la même commande. La
     * lecture d'idempotence se fait sous le verrou, mais un SELECT non
     * verrouillant lit un instantané — si la ligne de l'autre requête y
     * échappait, seule la contrainte en base arrêterait l'écriture. On
     * simule précisément cet ordre : la vérification ne voit rien, la base
     * refuse, et le service doit rendre le mouvement déjà enregistré au lieu
     * d'une 500 (qu'une passerelle traduirait en nouvelle tentative).
     */
    public function test_earning_recovers_from_a_concurrent_commit_without_a_500(): void
    {
        $card = app(LoyaltyCardService::class)
            ->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        // Ce que l'AUTRE requête a déjà écrit, invisible pour notre instantané.
        $deja = $card->transactions()->create([
            'type' => Transaction::TYPE_EARN, 'points_delta' => 30,
            'reference' => 'course-1', 'status' => 1,
        ]);

        $svc = \Mockery::mock(LoyaltyCardService::class, [])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $svc->shouldReceive('existingTransaction')->twice()->andReturn(null, $deja);

        $rendu = $svc->earnPoints($card, 30, ['reference' => 'course-1']);

        $this->assertSame($deja->id, $rendu->id);
        // Les points de l'autre requête, jamais comptés deux fois : la
        // transaction entière est annulée par le refus de la base.
        $this->assertEquals(0, $card->fresh()->points);
        $this->assertSame(1, Transaction::where('card_id', $card->id)->count());
    }

    /** Backstop DB : la contrainte unique protège même un appel qui ne passerait pas par le verrou du service. */
    public function test_the_database_rejects_a_duplicate_card_reference_outside_the_service(): void
    {
        $svc = app(LoyaltyCardService::class);
        $card = $svc->issueCard($this->program(), ['cardholder_name' => 'Jean'])['card'];

        $card->transactions()->create(['type' => Transaction::TYPE_EARN, 'reference' => 'raw-REF1', 'status' => 1]);

        $this->expectException(QueryException::class);
        $card->transactions()->create(['type' => Transaction::TYPE_EARN, 'reference' => 'raw-REF1', 'status' => 1]);
    }
}
