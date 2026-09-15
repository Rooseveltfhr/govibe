<?php

use Modules\Core\Support\HaitianCurrency;

it('converts gourdes to Haitian dollars at five to one', function () {
    expect(HaitianCurrency::toHaitianDollars(500))->toBe(100)
        ->and(HaitianCurrency::toHaitianDollars(5))->toBe(1)
        ->and(HaitianCurrency::toHaitianDollars(0))->toBe(0);
});

it('converts Haitian dollars back to gourdes', function () {
    expect(HaitianCurrency::fromHaitianDollars(100))->toBe(500)
        ->and(HaitianCurrency::fromHaitianDollars(20))->toBe(100);
});

it('round-trips any amount that is a whole number of Haitian dollars', function () {
    foreach ([5, 50, 125, 500, 1000, 2455] as $gourdes) {
        expect(HaitianCurrency::fromHaitianDollars(HaitianCurrency::toHaitianDollars($gourdes)))
            ->toBe($gourdes);
    }
});

// Pwen ki pi enpòtan an: pito nou pa di anyen pase nou di yon chif awondi.
// Yon kliyan ki tande « 25 dola » pou 123 goud ap tann 125 goud.
it('refuses to state a dollar figure when the amount is not exactly divisible', function () {
    expect(HaitianCurrency::toHaitianDollars(123))->toBeNull()
        ->and(HaitianCurrency::toHaitianDollars(501))->toBeNull()
        ->and(HaitianCurrency::toHaitianDollars(1))->toBeNull();
});

it('formats gourdes without decimals and with a thousands separator', function () {
    expect(HaitianCurrency::formatGourdes(500))->toBe('500 goud')
        ->and(HaitianCurrency::formatGourdes(1500))->toBe('1 500 goud')
        ->and(HaitianCurrency::formatGourdes(0))->toBe('0 goud');
});

it('describes a price with the dollar equivalent when it is exact', function () {
    expect(HaitianCurrency::describe(500))->toBe('500 goud (100 dola ayisyen)')
        ->and(HaitianCurrency::describe(500, 'fr'))->toBe('500 goud (100 dollars haïtiens)')
        ->and(HaitianCurrency::describe(500, 'en'))->toBe('500 goud (100 Haitian dollars)');
});

it('describes a price without an equivalent when it is not exact', function () {
    expect(HaitianCurrency::describe(123))->toBe('123 goud')
        ->and(HaitianCurrency::describe(123, 'fr'))->toBe('123 goud');
});
