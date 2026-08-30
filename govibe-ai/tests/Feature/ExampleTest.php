<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Rasin nan pa gen paj pa l ankò: li mennen sou katalòg ajan an, ki se sa
// yon vizitè vin chèche.
it('sends the root to the agent catalogue', function () {
    $this->get('/')->assertRedirect('/agents');
});

it('renders the catalogue', function () {
    $this->get('/agents')->assertOk()->assertSee('LOUVIA');
});
