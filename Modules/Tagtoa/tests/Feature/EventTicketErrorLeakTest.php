<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — une panne de base ne s'affiche jamais sur la billetterie
|--------------------------------------------------------------------------
| Trouvé par /security-review. En PHP, QueryException hérite de
| PDOException, qui hérite de RuntimeException : le `catch (\RuntimeException)`
| posé pour afficher « Stock insuffisant » attrapait donc aussi les pannes
| de base, et en affichait le message — la requête SQL complète avec ses
| valeurs — sur une page PUBLIQUE. createOrder() écrit la commande, les
| billets et le compteur de ventes DANS ce try, donc le cas est atteignable
| (interblocage, collision de code billet) plutôt que théorique.
|
| Et le plus gênant n'est pas la fuite : un achat réellement planté
| s'affichait à l'acheteur comme une mauvaise quantité.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Services\Event\TicketService;
use Modules\Tagtoa\Tests\TestCase;

class EventTicketErrorLeakTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create([
            'tenant_id' => null, 'title' => 'Gala', 'alias' => 'gala-leak',
            'currency' => 'HTG', 'is_published' => true,
        ]);
    }

    public function test_a_database_error_is_never_rendered_on_the_public_ticket_page(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create([
            'name' => 'Standard', 'price' => 500, 'is_active' => true, 'quantity' => 10,
        ]);

        $svc = \Mockery::mock(TicketService::class);
        $svc->shouldReceive('createOrder')->andThrow(new QueryException(
            'sqlite',
            'insert into "tagtoa_ev_orders" ("buyer_name", "buyer_phone") values (?, ?)',
            ['Wilner', '38112345'],
            new \Exception('SQLSTATE[40001]: Serialization failure: deadlock detected')
        ));
        $this->app->instance(TicketService::class, $svc);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->post(route('tagtoa.event.buy', $event->alias), [
            'buyer_name' => 'Wilner', 'buyer_phone' => '38112345',
            'qty' => [$type->id => 1],
        ]);
    }

    /** La règle métier, elle, reste bien affichée à l'acheteur. */
    public function test_a_business_rule_is_still_shown_to_the_buyer(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create([
            'name' => 'Standard', 'price' => 500, 'is_active' => true, 'quantity' => 1,
        ]);

        $this->post(route('tagtoa.event.buy', $event->alias), [
            'buyer_name' => 'Wilner', 'buyer_phone' => '38112345',
            'qty' => [$type->id => 5], // au-delà du stock
        ])->assertSessionHasErrors('qty');
    }

    /** Même garantie sur l'écran organisateur (invitation d'un VIP). */
    public function test_a_database_error_is_never_rendered_on_the_organiser_screen(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create([
            'name' => 'VIP', 'price' => 0, 'is_active' => true, 'is_vip' => true, 'quantity' => 5,
        ]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => null]));

        $svc = \Mockery::mock(TicketService::class);
        $svc->shouldReceive('inviteGuest')->andThrow(new QueryException(
            'sqlite',
            'insert into "tagtoa_ev_tickets" ("code") values (?)',
            ['TKT-COLLISION'],
            new \Exception('UNIQUE constraint failed: tagtoa_ev_tickets.code')
        ));
        $this->app->instance(TicketService::class, $svc);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->post(route('tagtoa.event.dashboard.guests.invite', $event->id), [
            'ticket_type_id' => $type->id, 'name' => 'Wilner', 'phone' => '38112345',
        ]);
    }
}
