<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEVISES (CUR-4) — billets d'événement en USD / devise personnalisée
|--------------------------------------------------------------------------
| Un type de billet peut avoir sa devise ; le serveur convertit avec les taux
| ACTIFS du commerce vers la devise de l'événement (vente en ligne ET guichet).
| Sans taux : refus, jamais « 5 $ vendus 5 G ». Le billet fige le prix d'origine.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Exceptions\TicketRuleException;
use Modules\Tagtoa\App\Models\Card\CardAccount;
use Modules\Tagtoa\App\Models\Currency\CurrencyRate;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Services\Card\CardWalletService;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Services\Event\TicketService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class CurrencyEventTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenant = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
        Tenant::flush();
    }

    private function evenement(string $tenant = 't-1'): Event
    {
        return Event::create([
            'tenant_id' => $tenant, 'title' => 'Gala', 'alias' => 'gala-'.$tenant, 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ]);
    }

    private function type(Event $e, array $attrs = []): TicketType
    {
        return TicketType::create(array_merge(['event_id' => $e->id, 'name' => 'VIP', 'price' => 5, 'currency' => 'USD', 'is_active' => true, 'quantity' => 10], $attrs));
    }

    private function taux(string $code = 'USD', float $rate = 132, string $tenant = 't-1'): void
    {
        app(CurrencyRateService::class)->save($tenant, 'HTG', ['code' => $code, 'rate' => $rate, 'confirm_jump' => true]);
    }

    private function acheter(Event $e, TicketType $t, int $qty = 1, array $buyer = [])
    {
        return app(TicketService::class)->createOrder($e, [['ticket_type_id' => $t->id, 'qty' => $qty]], $buyer + ['name' => 'Marie', 'phone' => '+50937000000']);
    }

    /* ---------- vente en ligne ---------- */

    public function test_an_online_order_converts_server_side_and_freezes_the_origin_on_each_ticket(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);

        $order = $this->acheter($e, $t, 2);

        $this->assertEquals(1320, $order->total, '2 × 5 $ × 132');
        $this->assertSame('HTG', $order->currency);
        $tk = Ticket::where('order_id', $order->id)->get();
        $this->assertCount(2, $tk);
        $this->assertSame('USD', $tk[0]->currency_original);
        $this->assertEquals(5, $tk[0]->unit_price_original);
        $this->assertEquals(132, $tk[0]->rate_used);
    }

    public function test_changing_the_rate_afterwards_does_not_touch_an_existing_ticket(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);
        $order = $this->acheter($e, $t);

        $this->taux('USD', 160);

        $this->assertEquals(660, $order->fresh()->total);
        $this->assertEquals(132, Ticket::where('order_id', $order->id)->first()->rate_used);
    }

    public function test_without_an_active_rate_nothing_is_sold_and_the_counter_is_untouched(): void
    {
        $e = $this->evenement();
        $t = $this->type($e);

        try {
            $this->acheter($e, $t);
            $this->fail('Vente sans taux acceptée.');
        } catch (TicketRuleException $ex) {
            $this->assertStringContainsString('devise', $ex->getMessage());
        }
        $this->assertSame(0, (int) $t->fresh()->sold);
        $this->assertSame(0, Order::count());
    }

    public function test_another_business_rate_is_never_used(): void
    {
        $e = $this->evenement('t-1');
        $this->taux('USD', 132, 't-2');
        $t = $this->type($e);

        $this->expectException(TicketRuleException::class);
        $this->acheter($e, $t);
    }

    public function test_a_deactivated_rate_refuses_the_sale(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);
        app(CurrencyRateService::class)->toggle(CurrencyRate::allTenants()->sole());

        $this->expectException(TicketRuleException::class);
        $this->acheter($e, $t);
    }

    public function test_a_tiny_rate_never_makes_a_ticket_free(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 0.000001);
        $t = $this->type($e, ['price' => 1]);

        $this->expectException(TicketRuleException::class);
        $this->acheter($e, $t);
    }

    public function test_an_amount_beyond_the_columns_is_refused(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 1000000);
        $t = $this->type($e, ['price' => 20000]);

        $this->expectException(TicketRuleException::class);
        $this->acheter($e, $t);
    }

    public function test_a_unit_price_beyond_the_column_is_refused_even_when_the_total_fits(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 1000000);
        $t = $this->type($e, ['price' => 200]);   // 2e8 par billet, total 2e8 < 1e10

        $this->expectException(TicketRuleException::class);
        $this->acheter($e, $t);
    }

    public function test_a_total_beyond_the_column_is_refused_even_when_each_price_fits(): void
    {
        $e = $this->evenement();
        $t = $this->type($e, ['currency' => null, 'price' => 99999999, 'quantity' => null]);

        try {
            $this->acheter($e, $t, 120);   // 1,2e10 > 9 999 999 999,99
            $this->fail('Total hors colonne accepté.');
        } catch (TicketRuleException $ex) {
            $this->assertStringContainsString('trop élevé', $ex->getMessage());
        }
        $this->assertSame(0, Order::count());
    }

    public function test_a_stale_expected_total_is_refused_with_the_new_total_and_rolls_back(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 160);
        $t = $this->type($e);   // le client a vu 660, le serveur calcule 800

        try {
            $this->acheter($e, $t, 1, ['expected_total' => 660]);
            $this->fail('Total périmé accepté.');
        } catch (TicketRuleException $ex) {
            $this->assertStringContainsString('800.00', $ex->getMessage());
        }
        $this->assertSame(0, (int) $t->fresh()->sold);
        $this->assertSame(0, Order::count());

        $this->assertEquals(800, $this->acheter($e, $t, 1, ['expected_total' => 800])->total);
    }

    public function test_native_currency_types_keep_their_exact_price_and_no_origin(): void
    {
        $e = $this->evenement();
        $t = $this->type($e, ['currency' => null, 'price' => 500]);

        $order = $this->acheter($e, $t, 2);

        $this->assertEquals(1000, $order->total);
        $this->assertNull(Ticket::where('order_id', $order->id)->first()->currency_original);
    }

    public function test_a_free_foreign_type_needs_no_rate(): void
    {
        $e = $this->evenement();
        $t = $this->type($e, ['price' => 0]);

        $this->assertEquals(0, $this->acheter($e, $t)->total);
    }

    public function test_the_public_form_posts_errors_back_instead_of_a_500(): void
    {
        $e = $this->evenement();
        $t = $this->type($e);

        $this->post(route('tagtoa.event.buy', $e->alias), ['buyer_name' => 'Marie', 'qty' => [$t->id => 1]])
            ->assertSessionHasErrors('qty');
        $this->assertSame(0, Order::count());
    }

    /* ---------- vente au guichet ---------- */

    private function guichet(Event $e, TicketType $t, array $extra = [])
    {
        $staff = Staff::create(['event_id' => $e->id, 'name' => 'Caissier', 'pin_hash' => bcrypt('1234'), 'role' => 'vente', 'active' => true]);

        return $this->withSession(['tagtoa_ev_staff.'.$e->id => ['id' => $staff->id, 'name' => $staff->name, 'role' => $staff->role]])
            ->postJson(route('tagtoa.event.staff.sell', $e->alias), array_merge([
                'name' => 'Marie', 'phone' => '+50937000000', 'ticket_type_id' => $t->id,
                'payment_method' => 'Carte TAGTOA', 'pay_card_code' => 'TG-CLIENT', 'pay_card_pin' => '4321', 'client_uuid' => 'ck-abc-123',
            ], $extra));
    }

    private function carte(float $balance = 2000): CardAccount
    {
        $svc = app(CardWalletService::class);
        $card = $svc->issue('UID-CLIENT', ['currency' => 'HTG', 'pin' => '4321', 'code' => 'TG-CLIENT']);
        $svc->topUp($card, $balance, ['reference' => 'seed-'.uniqid()]);

        return $card->fresh();
    }

    public function test_a_door_sale_debits_the_converted_price_and_freezes_the_origin(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);
        $card = $this->carte(2000);

        $this->guichet($e, $t)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1340, $card->fresh()->balance_minor, '2000 − 5 $ × 132');
        $tk = Ticket::where('event_id', $e->id)->sole();
        $this->assertSame('USD', $tk->currency_original);
        $this->assertEquals(132, $tk->rate_used);
    }

    public function test_a_door_sale_without_a_rate_charges_nothing_and_issues_nothing(): void
    {
        $e = $this->evenement();
        $t = $this->type($e);
        $card = $this->carte(2000);

        $this->guichet($e, $t)->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(2000, $card->fresh()->balance_minor);
        $this->assertSame(0, Ticket::where('event_id', $e->id)->count());
    }

    public function test_a_printed_ticket_sold_in_a_foreign_type_freezes_the_origin(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);
        $card = $this->carte(2000);
        $p = Ticket::create(['event_id' => $e->id, 'ticket_type_id' => $t->id, 'code' => 'PRN-1', 'serial' => 'S-PRN-1', 'status' => Ticket::STATUS_UNSOLD]);

        $this->guichet($e, $t, ['printed_code' => 'PRN-1'])->assertOk();

        $this->assertSame(1340, $card->fresh()->balance_minor);
        $p->refresh();
        $this->assertSame('USD', $p->currency_original);
        $this->assertEquals(132, $p->rate_used);
    }

    public function test_a_retry_after_the_rate_changed_keeps_the_original_debit_as_the_truth(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);
        $card = $this->carte(2000);
        $this->guichet($e, $t)->assertOk();
        $code = Ticket::where('event_id', $e->id)->sole()->code;
        // Coupure simulée : le débit existe mais le billet n'a jamais été lié.
        $txn = \Modules\Tagtoa\App\Models\Card\CardTxn::where('reference', 'like', 'evsale-%')->sole();
        $txn->update(['meta' => []]);
        Ticket::where('event_id', $e->id)->delete();
        $t->update(['sold' => 0]);

        $this->taux('USD', 160);
        $this->guichet($e, $t)->assertOk();

        $this->assertSame(1340, $card->fresh()->balance_minor, 'Pas de second débit.');
        $tk = Ticket::where('event_id', $e->id)->sole();
        $this->assertEquals(132, $tk->rate_used, 'Taux déduit du montant réellement débité.');
    }

    public function test_a_retry_after_the_rate_was_removed_still_completes_the_paid_sale(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);
        $card = $this->carte(2000);
        $this->guichet($e, $t)->assertOk();
        \Modules\Tagtoa\App\Models\Card\CardTxn::where('reference', 'like', 'evsale-%')->sole()->update(['meta' => []]);
        Ticket::where('event_id', $e->id)->delete();
        $t->update(['sold' => 0]);

        app(CurrencyRateService::class)->toggle(CurrencyRate::allTenants()->sole());
        $this->guichet($e, $t)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1340, $card->fresh()->balance_minor);
        $this->assertSame(1, Ticket::where('event_id', $e->id)->count());
    }

    public function test_a_free_event_needs_no_rate_even_for_a_foreign_type(): void
    {
        $e = $this->evenement();
        $e->update(['is_free' => true]);
        $t = $this->type($e);

        $this->assertEquals(0, $this->acheter($e, $t)->total);
        $html = $this->get(route('tagtoa.event.show', $e->alias))->getContent();
        $this->assertStringNotContainsString('Indisponible', $html);
    }

    public function test_the_directory_never_calls_a_priced_event_free_when_the_rate_is_missing(): void
    {
        $e = $this->evenement();
        $this->type($e);

        $html = $this->get(route('tagtoa.events.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Prix indisponible', $html);
        $this->assertStringNotContainsString('Gratuit', $html);
    }

    public function test_the_page_recomputes_the_expected_total_on_load_and_restore(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $this->type($e);

        $html = $this->get(route('tagtoa.event.show', $e->alias))->getContent();
        $this->assertStringContainsString("addEventListener('pageshow',tot)", $html);
    }

    /* ---------- pages publiques ---------- */

    public function test_the_public_page_shows_the_original_price_and_a_total_in_the_event_currency(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $this->type($e);

        $html = $this->get(route('tagtoa.event.show', $e->alias))->assertOk()->getContent();

        $this->assertStringContainsString('≈ 660.00 HTG', $html);
        $this->assertMatchesRegularExpression('/data-price="660"/', $html);
        $this->assertStringContainsString('name="expected_total"', $html);
    }

    public function test_without_a_rate_the_public_page_marks_the_ticket_unavailable(): void
    {
        $e = $this->evenement();
        $t = $this->type($e);

        $html = $this->get(route('tagtoa.event.show', $e->alias))->assertOk()->getContent();

        $this->assertStringContainsString('Indisponible', $html);
        $this->assertStringNotContainsString('name="qty['.$t->id.']"', $html);
    }

    public function test_the_directory_shows_the_converted_minimum_price(): void
    {
        $e = $this->evenement();
        $this->taux('USD', 132);
        $this->type($e);
        $this->type($e, ['name' => 'Std', 'currency' => null, 'price' => 1000]);

        $this->get(route('tagtoa.events.index'))->assertOk()->assertSee('660.00 HTG', false);
    }

    /* ---------- tableau de bord ---------- */

    private function enregistrer(Event $e, array $over = [], array $types = null)
    {
        return $this->put(route('tagtoa.event.dashboard.update', $e->id), array_merge([
            'title' => 'Gala', 'currency' => 'HTG',
            'ticket_types' => $types ?? [['name' => 'VIP', 'price' => 5, 'currency' => 'USD', 'is_active' => 1]],
        ], $over));
    }

    public function test_the_merchant_sets_a_type_currency_only_from_the_allowed_choices(): void
    {
        $this->patron();
        $e = $this->evenement();
        $this->taux('USD', 132);

        $this->enregistrer($e, [], [['name' => 'VIP', 'price' => 5, 'currency' => 'usd', 'is_active' => 1]])->assertSessionHasNoErrors();
        $this->assertSame('USD', TicketType::sole()->currency);

        $this->enregistrer($e, [], [['name' => 'VIP', 'price' => 5, 'currency' => 'HTG', 'is_active' => 1]])->assertSessionHasNoErrors();
        $this->assertNull(TicketType::sole()->currency);

        $this->enregistrer($e, [], [['name' => 'VIP', 'price' => 5, 'currency' => 'EUR', 'is_active' => 1]])->assertSessionHasErrors('ticket_types');
        $this->assertNull(TicketType::sole()->currency, 'Rien d\'écrit.');
    }

    public function test_a_rate_of_another_business_cannot_be_used_on_my_type(): void
    {
        $this->patron('t-1');
        $e = $this->evenement('t-1');
        $this->taux('USD', 132, 't-2');

        $this->enregistrer($e)->assertSessionHasErrors('ticket_types');
        $this->assertSame(0, TicketType::count());
    }

    public function test_a_failed_validation_writes_nothing_not_even_the_event(): void
    {
        $this->patron();
        $e = $this->evenement();
        $this->taux('USD', 132);

        $this->enregistrer($e, ['title' => 'Autre', 'currency' => 'USD'], [['name' => 'VIP', 'price' => 5, 'currency' => 'EUR']])
            ->assertSessionHasErrors('ticket_types');

        $this->assertSame('HTG', $e->fresh()->currency);
        $this->assertSame('Gala', $e->fresh()->title);
    }

    public function test_saving_without_the_currency_field_keeps_the_type_currency(): void
    {
        $this->patron();
        $e = $this->evenement();
        $this->taux('USD', 132);
        $t = $this->type($e);

        $this->enregistrer($e, [], [['id' => $t->id, 'name' => 'VIP', 'price' => 6, 'is_active' => 1]])->assertSessionHasNoErrors();

        $this->assertSame('USD', $t->fresh()->currency);
        $this->assertEquals(6, $t->fresh()->price);
    }

    public function test_array_currency_and_absurd_prices_are_validation_errors(): void
    {
        $this->patron();
        $e = $this->evenement();

        $this->enregistrer($e, [], [['name' => 'A', 'price' => 1, 'currency' => ['x']]])->assertSessionHasErrors('ticket_types');
        $this->enregistrer($e, [], [['name' => 'A', 'price' => 1e12]])->assertSessionHasErrors('ticket_types');
        $this->assertSame(0, TicketType::count());
    }

    public function test_the_event_currency_is_normalised_and_validated(): void
    {
        $this->patron();
        $e = $this->evenement();

        $this->enregistrer($e, ['currency' => ' usd '], [])->assertSessionHasNoErrors();
        $this->assertSame('USD', $e->fresh()->currency);

        $this->enregistrer($e, ['currency' => 'x y!'], [])->assertSessionHasErrors('currency');
    }

    public function test_the_edit_form_offers_the_event_currency_and_active_rates_only(): void
    {
        $this->patron();
        $e = $this->evenement();
        $this->taux('USD', 132);
        $this->taux('EUR', 150);
        app(CurrencyRateService::class)->toggle(CurrencyRate::allTenants()->where('code', 'EUR')->sole());

        $html = $this->get(route('tagtoa.event.dashboard.edit', $e->id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="ticket_types\[IDX\]\[currency\]"/', $html);
        $this->assertStringContainsString('value="USD"', $html);
        $this->assertDoesNotMatchRegularExpression('/ticket_types\[IDX\]\[currency\].*value="EUR"/s', substr($html, strpos($html, 'tttpl'), 1500));
    }

    public function test_the_currency_screen_accepts_an_event_currency_as_base(): void
    {
        $this->patron();
        Event::create(['tenant_id' => 't-1', 'title' => 'Euro', 'alias' => 'euro', 'currency' => 'EUR', 'is_published' => true,
            'starts_at' => now()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString()]);

        $this->post(route('tagtoa.currencies.store'), ['code' => 'USD', 'base_currency' => 'EUR', 'rate' => '0.92'])->assertSessionHasNoErrors();
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000189_add_currency_to_event_ticket_types.php';
        $mig->up();
        $mig->up();
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_ev_ticket_types', 'currency'));
        $this->assertFalse(Schema::hasColumn('tagtoa_ev_tickets', 'rate_used'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_ev_tickets', 'currency_original'));
    }
}
