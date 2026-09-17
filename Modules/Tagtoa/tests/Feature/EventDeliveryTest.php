<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — journal de livraison des confirmations CLIENT
|--------------------------------------------------------------------------
| EventNotifier::notifyCustomer() doit journaliser CHAQUE tentative, avec le
| résultat RÉEL (jamais supposé) de NotificationService — d'où le double de
| ce service plutôt que le réseau (même raisonnement que pour LOYALTY :
| Mail::raw() est un no-op sous Mail::fake(), donc invérifiable ainsi).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Delivery;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Services\Event\EventNotifier;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\Tests\TestCase;

class EventDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function event(string $tenantId = 't-1'): Event
    {
        return Event::create(['tenant_id' => $tenantId, 'title' => 'Gala', 'alias' => 'gala-'.random_int(1000, 9999), 'currency' => 'HTG', 'is_published' => true]);
    }

    public function test_un_envoi_reussi_est_journalise_comme_livre(): void
    {
        $event = $this->event();
        $mock = \Mockery::mock(NotificationService::class);
        $mock->shouldReceive('email')->once()->andReturn(true);
        $this->app->instance(NotificationService::class, $mock);

        app(EventNotifier::class)->notifyCustomer($event, Delivery::CONTEXT_ORDER_CONFIRMATION, 'Sujet', 'Corps', 'client@ex.com', null);

        $d = Delivery::where('event_id', $event->id)->first();
        $this->assertSame(Delivery::STATUS_SENT, $d->status);
        $this->assertSame('email', $d->channel);
        $this->assertSame('client@ex.com', $d->recipient);
    }

    public function test_un_envoi_qui_echoue_est_journalise_comme_non_livre_sans_lever_dexception(): void
    {
        $event = $this->event();
        $mock = \Mockery::mock(NotificationService::class);
        $mock->shouldReceive('whatsapp')->once()->andReturn(false);
        $this->app->instance(NotificationService::class, $mock);

        app(EventNotifier::class)->notifyCustomer($event, Delivery::CONTEXT_CHECKIN_CONFIRMATION, 'Sujet', 'Corps', null, '38112345');

        $d = Delivery::where('event_id', $event->id)->first();
        $this->assertSame(Delivery::STATUS_NOT_SENT, $d->status);
    }

    public function test_une_exception_du_canal_est_absorbee_et_journalisee_non_livree(): void
    {
        $event = $this->event();
        $mock = \Mockery::mock(NotificationService::class);
        $mock->shouldReceive('email')->once()->andThrow(new \RuntimeException('SMTP down'));
        $this->app->instance(NotificationService::class, $mock);

        app(EventNotifier::class)->notifyCustomer($event, Delivery::CONTEXT_ORDER_CONFIRMATION, 'Sujet', 'Corps', 'client@ex.com', null);

        $d = Delivery::where('event_id', $event->id)->first();
        $this->assertNotNull($d);
        $this->assertSame(Delivery::STATUS_NOT_SENT, $d->status);
    }

    public function test_aucun_destinataire_ne_journalise_rien_sur_ce_canal(): void
    {
        $event = $this->event();
        $mock = \Mockery::mock(NotificationService::class);
        $mock->shouldNotReceive('email');
        $mock->shouldNotReceive('whatsapp');
        $this->app->instance(NotificationService::class, $mock);

        app(EventNotifier::class)->notifyCustomer($event, Delivery::CONTEXT_ORDER_CONFIRMATION, 'Sujet', 'Corps', null, null);

        $this->assertSame(0, Delivery::where('event_id', $event->id)->count());
    }

    public function test_deux_canaux_produisent_deux_lignes_distinctes(): void
    {
        $event = $this->event();
        $mock = \Mockery::mock(NotificationService::class);
        $mock->shouldReceive('email')->once()->andReturn(true);
        $mock->shouldReceive('whatsapp')->once()->andReturn(true);
        $this->app->instance(NotificationService::class, $mock);

        app(EventNotifier::class)->notifyCustomer($event, Delivery::CONTEXT_ORDER_CONFIRMATION, 'Sujet', 'Corps', 'c@ex.com', '38112345');

        $this->assertSame(2, Delivery::where('event_id', $event->id)->count());
    }

    public function test_lecran_de_statistiques_est_cloisonne_par_commerce(): void
    {
        $this->patron('t-1');
        $autre = $this->event('t-2');

        $this->get(route('tagtoa.event.dashboard.deliveries', $autre->id))->assertNotFound();
    }

    public function test_lecran_de_statistiques_affiche_les_totaux(): void
    {
        $this->patron();
        $event = $this->event();
        Delivery::create(['event_id' => $event->id, 'context' => 'order_confirmation', 'channel' => 'email', 'recipient' => 'a@ex.com', 'status' => 'sent']);
        Delivery::create(['event_id' => $event->id, 'context' => 'order_confirmation', 'channel' => 'whatsapp', 'recipient' => '38112345', 'status' => 'not_sent']);

        $response = $this->get(route('tagtoa.event.dashboard.deliveries', $event->id));

        $response->assertOk();
        $response->assertSee('2', false); // total tentatives (voir grid g3)
    }
}
