<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves the landing page at the root', function () {
    $this->get('/')->assertOk()->assertSee('LOUVIA');
});

it('renders the catalogue', function () {
    $this->get('/agents')->assertOk()->assertSee('LOUVIA');
});
