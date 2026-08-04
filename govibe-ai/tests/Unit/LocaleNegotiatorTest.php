<?php

use Modules\Core\Support\LocaleNegotiator;

function negotiator(): LocaleNegotiator
{
    return new LocaleNegotiator(['fr', 'ht', 'en', 'es'], 'fr');
}

it('prefers the explicit query parameter', function () {
    expect(negotiator()->resolve('ht', 'en', 'es', 'en'))->toBe('ht');
});

it('falls back to session then cookie', function () {
    expect(negotiator()->resolve(null, 'es', 'en', null))->toBe('es');
    expect(negotiator()->resolve(null, null, 'en', null))->toBe('en');
});

it('ignores unsupported values at every level', function () {
    expect(negotiator()->resolve('zz', 'xx', 'yy', 'de'))->toBe('fr');
});

it('parses Accept-Language with quality factors', function () {
    expect(negotiator()->fromAcceptLanguage('de, es;q=0.7, en;q=0.9'))->toBe('en');
});

it('maps regional tags to their primary language', function () {
    expect(negotiator()->fromAcceptLanguage('fr-FR, en-US;q=0.5'))->toBe('fr');
});

it('returns null when nothing matches', function () {
    expect(negotiator()->fromAcceptLanguage('de, pt;q=0.8'))->toBeNull();
});

it('handles a malformed header without crashing', function () {
    expect(negotiator()->fromAcceptLanguage(';;;,, q=,'))->toBeNull();
});
