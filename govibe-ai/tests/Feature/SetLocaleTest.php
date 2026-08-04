<?php

it('uses french by default when nothing matches', function () {
    $this->withHeaders(['Accept-Language' => 'de'])->get('/');

    expect(app()->getLocale())->toBe('fr');
});

it('switches locale with the lang query parameter', function () {
    $this->get('/?lang=ht')->assertOk()->assertSee('Byenveni sou GOVIBE AI');

    expect(app()->getLocale())->toBe('ht');
});

it('remembers an explicit choice in the session', function () {
    $this->get('/?lang=es');
    $this->get('/');

    expect(app()->getLocale())->toBe('es');
});

it('negotiates from the Accept-Language header', function () {
    $this->withHeaders(['Accept-Language' => 'de, en;q=0.8, fr;q=0.5'])->get('/');

    expect(app()->getLocale())->toBe('en');
});

it('ignores unsupported locales', function () {
    $this->withHeaders(['Accept-Language' => 'de'])->get('/?lang=zz');

    expect(app()->getLocale())->toBe('fr');
});
