<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| Locale::sanitizeSelection() / Locale::forMenu() — PUR (aucun Eloquent,
| aucun I/O). Un menu déclare quelles langues il offre au client, un
| sous-ensemble des 4 langues globales de TAGTOA (fr/ht/en/es, défaut fr).
|
| RefreshDatabase pour la même raison que LoyaltyMovementMessageTest : le
| traducteur Laravel n'est pas requis ici, mais Testbench rétrograde sinon
| une migration pré-existante qui échoue sous SQLite, sans rapport avec ce
| test.
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Support\Locale;
use Modules\Tagtoa\Tests\TestCase;

class LocaleLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_something_other_than_an_array_means_no_restriction(): void
    {
        $this->assertNull(Locale::sanitizeSelection(null));
        $this->assertNull(Locale::sanitizeSelection('fr'));
        $this->assertNull(Locale::sanitizeSelection(false));
    }

    public function test_unknown_codes_are_dropped_silently(): void
    {
        $result = Locale::sanitizeSelection(['fr', 'klingon', 'ht']);

        $this->assertSame(['fr', 'ht'], $result);
    }

    public function test_the_default_locale_is_always_included_even_if_not_submitted(): void
    {
        $result = Locale::sanitizeSelection(['en']);

        $this->assertContains('fr', $result);
        $this->assertContains('en', $result);
    }

    public function test_selecting_every_language_collapses_to_no_restriction(): void
    {
        $result = Locale::sanitizeSelection(['fr', 'ht', 'en', 'es']);

        $this->assertNull($result);
    }

    public function test_a_partial_selection_is_kept_as_is(): void
    {
        $result = Locale::sanitizeSelection(['fr', 'ht']);

        $this->assertSame(['fr', 'ht'], $result);
    }

    public function test_a_menu_without_a_restriction_offers_every_language(): void
    {
        $this->assertSame(Locale::codes(), Locale::forMenu(null));
    }

    public function test_a_restricted_menu_offers_only_its_chosen_languages(): void
    {
        $this->assertSame(['fr', 'ht'], Locale::forMenu(['fr', 'ht']));
    }
}
