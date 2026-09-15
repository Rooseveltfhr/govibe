<?php

use Modules\AIRouter\Support\LanguageDetector;

it('detects haitian creole', function () {
    expect((new LanguageDetector)->detect('Bonjou, mwen vle konnen kijan pou m fè yon fakti'))->toBe('ht');
});

it('does not confuse creole with french', function () {
    expect((new LanguageDetector)->detect('Kisa ou vle fè jodi a? Mèsi anpil'))->toBe('ht');
});

it('detects french', function () {
    expect((new LanguageDetector)->detect('Bonjour, je voudrais savoir comment créer une facture'))->toBe('fr');
});

it('detects english', function () {
    expect((new LanguageDetector)->detect('Hello, what is the price for this service please'))->toBe('en');
});

it('detects spanish', function () {
    expect((new LanguageDetector)->detect('Hola, quiero saber el precio, muchas gracias'))->toBe('es');
});

it('returns null when there is no signal', function () {
    expect((new LanguageDetector)->detect('42 :: ###'))->toBeNull();
});
