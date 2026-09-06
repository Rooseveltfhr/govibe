<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agents\Models\AgentOrder;

uses(RefreshDatabase::class);

function orderPayload(array $overrides = []): array
{
    return array_merge([
        'sector' => 'restaurant',
        'business_name' => 'Chita & Manje',
        'contact_name' => 'Roosevelt',
        'whatsapp' => '+509 3398 8754',
        'mode' => 'expert',
        'channels' => ['whatsapp', 'website'],
        'notes' => 'Pran kòmand aswè, mande adrès ak yon repè.',
    ], $overrides);
}

it('offers the order form, with a model already chosen from a card', function () {
    $this->get(route('orders.create'))->assertOk()->assertSee(__('Commander un agent'));

    $this->get(route('orders.create', 'clinic'))
        ->assertOk()
        ->assertSee('value="clinic" selected', false);
});

it('refuses a model that is not in the catalogue', function () {
    $this->get(route('orders.create', 'hospital'))->assertNotFound();
});

// Se chemen an pou moun ki pa abil: yo pa ranpli okenn fich konesans, yo
// mande yon ekspè.
it('records a request for an expert to build the agent', function () {
    $this->post(route('orders.store'), orderPayload())->assertRedirect();

    $order = AgentOrder::firstOrFail();

    expect($order->business_name)->toBe('Chita & Manje')
        ->and($order->wantsExpert())->toBeTrue()
        ->and($order->channels)->toBe(['whatsapp', 'website'])
        ->and($order->status)->toBe('nouvo')
        ->and($order->reference)->toMatch('/^LV-\d{8}-[A-Z2-9]{4}$/');
});

it('keeps WhatsApp mandatory — it is the only channel that reaches people here', function () {
    $this->post(route('orders.store'), orderPayload(['whatsapp' => '']))
        ->assertSessionHasErrors('whatsapp');

    expect(AgentOrder::count())->toBe(0);
});

it('rejects a model, a mode or a channel it does not know', function () {
    $this->post(route('orders.store'), orderPayload(['sector' => 'hospital']))->assertSessionHasErrors('sector');
    $this->post(route('orders.store'), orderPayload(['mode' => 'gratis']))->assertSessionHasErrors('mode');
    $this->post(route('orders.store'), orderPayload(['channels' => ['fax']]))->assertSessionHasErrors('channels.0');

    expect(AgentOrder::count())->toBe(0);
});

it('shows the confirmation with the reference and a ready-made WhatsApp message', function () {
    $this->post(route('orders.store'), orderPayload());
    $order = AgentOrder::firstOrFail();

    $this->get(route('orders.show', $order->reference))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('Chita &amp; Manje', false)
        ->assertSee('https://wa.me/50933988754?text=', false);
});

// Yon referans ki swiv (1, 2, 3…) ta kite nenpòt moun li kòmand tout lòt
// moun: non biznis, nimewo WhatsApp, sa yo bezwen.
it('does not let a confirmation page be walked from one order to the next', function () {
    foreach (['A', 'B'] as $name) {
        $this->post(route('orders.store'), orderPayload(['business_name' => $name]));
    }

    $references = AgentOrder::pluck('reference')->all();

    expect($references[0])->not->toBe($references[1]);

    $this->get('/komande/LV-20260101-AAAA/konfimasyon')->assertNotFound();
});

it('offers the self-service path its own next step', function () {
    $this->post(route('orders.store'), orderPayload(['mode' => 'self']));
    $order = AgentOrder::firstOrFail();

    $this->get(route('orders.show', $order->reference))
        ->assertOk()
        ->assertSee(route('agents.create', 'restaurant'));
});
