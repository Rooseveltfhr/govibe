<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — plus de langue devinée depuis le téléphone du marchand
|--------------------------------------------------------------------------
| Signalé en testant comme un vrai marchand : un commerçant dont le
| téléphone est en anglais, qui n'a JAMAIS choisi de langue (ni ?lang=, ni
| session, ni cookie), se retrouvait avec un tableau de bord mélangé —
| la plupart des textes TAGTOA sont codés en dur en français. L'en-tête
| Accept-Language du navigateur ne doit plus influencer la langue choisie :
| sans choix explicite, c'est toujours le français (la langue par défaut)
| qui s'affiche, jamais une supposition.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
    }

    public function test_an_english_phone_with_no_explicit_choice_still_gets_french(): void
    {
        $this->patron();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/tagtoa/home')
            ->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_an_explicit_lang_query_param_still_wins_over_the_browser(): void
    {
        $this->patron();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/tagtoa/home?lang=en')
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_locale_chosen_earlier_is_remembered_even_with_an_english_phone(): void
    {
        $this->patron();

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->withSession(['tagtoa_locale' => 'ht'])
            ->get('/tagtoa/home')
            ->assertOk();

        $this->assertSame('ht', app()->getLocale());
    }
}
