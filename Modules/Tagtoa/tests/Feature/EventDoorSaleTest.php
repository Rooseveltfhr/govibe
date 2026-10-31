<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — CS-5 : une vente au guichet est atomique
|--------------------------------------------------------------------------
| Avant, StaffTerminalController::sell() :
|   - débitait la carte, PUIS vérifiait le billet pré-imprimé (introuvable /
|     déjà vendu) : client débité, vente refusée ;
|   - n'annulait JAMAIS le débit si l'émission du billet échouait ensuite
|     (« Réessayez ») : client débité, pas de billet ;
|   - réclamait un billet pré-imprimé en deux temps (lire « invendu » puis
|     écrire) : deux vendeurs pouvaient le vendre tous deux ;
|   - n'incrémentait jamais `sold` : le quota dérivait.
| Décision D2 (défaut du plan) : remboursement automatique, avec trace d'audit.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Actions\Event\Wallet\EncodeParticipantCard;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Card\CardAccount;
use Modules\Tagtoa\App\Models\Card\CardTxn;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Services\Card\CardWalletService;
use Modules\Tagtoa\Tests\TestCase;

class ThrowingEncoder extends EncodeParticipantCard
{
    public function __construct() {}

    public function handle(Event $event, array $data): array
    {
        throw new \RuntimeException('SQLSTATE[HY000] boom secret_table');
    }
}

class EventDoorSaleTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Staff $staff;
    private TicketType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::create([
            'tenant_id' => 't-1', 'title' => 'Gala', 'alias' => 'gala', 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ]);
        $this->staff = Staff::create(['event_id' => $this->event->id, 'name' => 'Caissier', 'pin_hash' => bcrypt('1234'), 'role' => 'vente', 'active' => true]);
        $this->type = TicketType::create(['event_id' => $this->event->id, 'name' => 'Standard', 'price' => 500, 'currency' => 'HTG', 'is_active' => true, 'quantity' => 10]);
    }

    private function card(float $balance = 2000): CardAccount
    {
        $svc = app(CardWalletService::class);
        $card = $svc->issue('UID-CLIENT', ['currency' => 'HTG', 'pin' => '4321', 'code' => 'TG-CLIENT']);
        $svc->topUp($card, $balance, ['reference' => 'seed-'.uniqid()]);

        return $card->fresh();
    }

    private function sell(array $extra = [])
    {
        return $this->withSession(['tagtoa_ev_staff.'.$this->event->id => ['id' => $this->staff->id, 'name' => $this->staff->name, 'role' => $this->staff->role]])
            ->postJson(route('tagtoa.event.staff.sell', $this->event->alias), array_merge([
                'name' => 'Marie', 'phone' => '+50937000000', 'ticket_type_id' => $this->type->id,
            ], $extra));
    }

    private function cardSale(array $extra = [])
    {
        return $this->sell(array_merge(['payment_method' => 'Carte TAGTOA', 'pay_card_code' => 'TG-CLIENT', 'pay_card_pin' => '4321', 'client_uuid' => 'ck-abc-123'], $extra));
    }

    private function printed(string $code = 'PRN-1'): Ticket
    {
        return Ticket::create(['event_id' => $this->event->id, 'ticket_type_id' => $this->type->id, 'code' => $code, 'serial' => 'S-'.$code, 'status' => Ticket::STATUS_UNSOLD]);
    }

    public function test_a_card_sale_debits_issues_the_ticket_and_counts_it_against_the_quota(): void
    {
        $card = $this->card(2000);

        $this->cardSale()->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1500, $card->fresh()->balance_minor, '2000 − 500 HTG');
        $t = Ticket::where('event_id', $this->event->id)->first();
        $this->assertSame(Ticket::STATUS_VALID, $t->status);
        $this->assertSame('Carte TAGTOA', $t->payment_method);
        $this->assertSame(1, (int) $this->type->fresh()->sold, 'Le quota doit suivre les ventes au guichet.');
    }

    public function test_a_printed_ticket_that_does_not_exist_is_refused_before_any_debit(): void
    {
        $card = $this->card(2000);

        $this->cardSale(['printed_code' => 'NOPE'])->assertStatus(422);

        $this->assertSame(2000, $card->fresh()->balance_minor, 'Refusée AVANT le débit : la carte n\'est pas touchée.');
        $this->assertSame(0, CardTxn::where('type', 'charge')->count());
    }

    public function test_an_already_sold_printed_ticket_is_refused_before_any_debit(): void
    {
        $card = $this->card(2000);
        $t = $this->printed();
        $t->update(['status' => Ticket::STATUS_VALID]);

        $this->cardSale(['printed_code' => 'PRN-1'])->assertStatus(422);

        $this->assertSame(2000, $card->fresh()->balance_minor);
    }

    public function test_a_printed_ticket_sold_by_another_seller_during_the_sale_is_refused_and_the_card_refunded(): void
    {
        $card = $this->card(2000);
        $t = $this->printed();

        // L'AUTRE vendeur valide le billet entre notre contrôle et notre réclamation.
        $flipped = false;
        DB::beforeExecuting(function (string $query) use (&$flipped, $t) {
            if (! $flipped && preg_match('/^update [`"]tagtoa_ev_tickets[`"]/', $query)) {
                $flipped = true;
                DB::connection()->getPdo()->exec('UPDATE tagtoa_ev_tickets SET status = '.Ticket::STATUS_VALID.', holder_name = \'Autre client\' WHERE id = '.$t->id);
            }
        });

        $res = $this->cardSale(['printed_code' => 'PRN-1'])->assertStatus(422);

        $this->assertTrue($flipped, 'Le scénario de course doit avoir eu lieu.');
        // (L'« autre vendeur » simulé partage la connexion de test : son écriture est annulée
        // avec la nôtre. Ce qui compte ici : NOTRE vente n'a pas pris le billet.)
        $this->assertNotSame('Marie', $t->fresh()->holder_name, 'Notre vente ne doit pas écraser le billet.');
        $this->assertSame(2000, $card->fresh()->balance_minor, 'Débit annulé.');
        $this->assertStringContainsString('remboursée', $res->json('message'));
    }

    public function test_a_failure_after_the_debit_refunds_the_card_logs_an_audit_entry_and_leaks_nothing(): void
    {
        $card = $this->card(2000);
        $this->app->bind(EncodeParticipantCard::class, fn () => new ThrowingEncoder());

        $res = $this->cardSale(['uid' => 'NFC-UID-9'])->assertStatus(422);

        $this->assertSame(2000, $card->fresh()->balance_minor, 'Le client n\'est pas débité sans billet.');
        $this->assertSame(0, Ticket::where('event_id', $this->event->id)->count());
        $this->assertSame(1, CardTxn::where('type', 'refund')->count());
        $this->assertStringNotContainsString('SQLSTATE', (string) $res->getContent());
        $this->assertStringNotContainsString('secret_table', (string) $res->getContent());
        $this->assertStringContainsString('remboursée', $res->json('message'));
        $this->assertSame(1, AuditLog::where('action', 'event_door_sale_refunded')->count());
        $this->assertSame(0, (int) $this->type->fresh()->sold, 'Le quota n\'est pas consommé par une vente échouée.');
    }

    public function test_a_sold_out_ticket_type_refuses_the_sale_and_refunds(): void
    {
        $card = $this->card(2000);
        $this->type->update(['quantity' => 1, 'sold' => 1]);

        $res = $this->cardSale()->assertStatus(422);

        $this->assertSame(2000, $card->fresh()->balance_minor);
        $this->assertStringContainsString('Plus de place', $res->json('message'));
        $this->assertSame(0, Ticket::where('event_id', $this->event->id)->count());
    }

    public function test_a_cash_sale_still_works_and_counts(): void
    {
        $this->sell(['payment_method' => 'Espèces'])->assertOk()->assertJson(['ok' => true]);

        $t = Ticket::where('event_id', $this->event->id)->first();
        $this->assertSame($this->staff->id, $t->sold_by_staff_id);
        $this->assertSame(1, (int) $this->type->fresh()->sold);
    }

    public function test_a_printed_ticket_is_activated_not_duplicated(): void
    {
        $t = $this->printed();

        $this->sell(['printed_code' => 'PRN-1', 'payment_method' => 'Espèces'])->assertOk();

        $this->assertSame(1, Ticket::where('event_id', $this->event->id)->count());
        $this->assertSame(Ticket::STATUS_VALID, $t->fresh()->status);
        $this->assertSame('Marie', $t->fresh()->holder_name);
        $this->assertSame(1, (int) $this->type->fresh()->sold);
    }

    public function test_a_very_long_client_uuid_cannot_break_the_card_debit(): void
    {
        $this->card(2000);

        $this->cardSale(['client_uuid' => str_repeat('x', 80)])->assertOk()->assertJson(['ok' => true]);

        $this->assertLessThanOrEqual(40, strlen((string) CardTxn::where('type', 'charge')->value('reference')), 'La colonne reference fait 40 caractères.');
    }

    public function test_a_wrong_pin_charges_nothing_and_issues_nothing(): void
    {
        $card = $this->card(2000);

        $this->cardSale(['pay_card_pin' => '0000'])->assertStatus(422);

        $this->assertSame(2000, $card->fresh()->balance_minor);
        $this->assertSame(0, Ticket::where('event_id', $this->event->id)->count());
    }
}
