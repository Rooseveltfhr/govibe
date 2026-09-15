<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Menu\Translatable;
use PHPUnit\Framework\TestCase;

/**
 * Traduire une carte sans dupliquer une ligne — logique pure, sans base.
 *
 * La règle qui gouverne tout ce fichier : un menu SANS la moindre traduction
 * doit s'afficher exactement comme avant. Aucune des cartes déjà publiées ne
 * doit changer d'apparence le jour où ce module arrive.
 */
class TranslatableTest extends TestCase
{
    /* ==================================================================
       resolve() — jamais un trou dans la carte
       ================================================================== */

    public function test_a_menu_with_no_translations_shows_exactly_what_it_showed_before(): void
    {
        // LA garde de non-régression. Rien n'a changé pour les milliers de
        // cartes déjà publiées : pas de tableau, le texte de base sort tel quel.
        $this->assertSame('Griot ak bannann', Translatable::resolve(null, 'Griot ak bannann', 'name', 'en'));
        $this->assertSame('Griot ak bannann', Translatable::resolve([], 'Griot ak bannann', 'name', 'en'));
    }

    public function test_a_translation_for_this_language_wins(): void
    {
        $traductions = ['en' => ['name' => 'Fried pork with plantains']];

        $this->assertSame('Fried pork with plantains', Translatable::resolve($traductions, 'Griot', 'name', 'en'));
    }

    public function test_a_missing_language_falls_back_to_the_base_text_not_a_blank(): void
    {
        // Un plat traduit en anglais mais pas en espagnol doit continuer
        // d'exister pour le client espagnol — dans la langue du marchand,
        // jamais un champ vide.
        $traductions = ['en' => ['name' => 'Fried pork']];

        $this->assertSame('Griot', Translatable::resolve($traductions, 'Griot', 'name', 'es'));
    }

    public function test_an_emptied_translation_is_treated_as_absent(): void
    {
        // Le marchand a ouvert le champ anglais puis l'a vidé : ce n'est pas
        // « le nom de ce plat est vide », c'est « pas de traduction ici ».
        $traductions = ['en' => ['name' => '   ']];

        $this->assertSame('Griot', Translatable::resolve($traductions, 'Griot', 'name', 'en'));
    }

    public function test_a_missing_base_text_resolves_to_an_empty_string_not_null(): void
    {
        // Jamais null : la vue peut toujours concaténer ou passer à htmlspecialchars.
        $this->assertSame('', Translatable::resolve(null, null, 'name', 'en'));
    }

    /* ==================================================================
       sanitize() — ce qui entre en base
       ================================================================== */

    public function test_only_known_languages_and_fields_survive(): void
    {
        $soumis = [
            'en' => ['name' => 'Fried pork', 'price' => '999'],   // 'price' n'est pas un champ traduisible
            'zz' => ['name' => 'Langue inventée'],                 // 'zz' n'existe pas
            'es' => ['name' => 'Cerdo frito'],
        ];

        $propre = Translatable::sanitize($soumis, ['fr', 'ht', 'en', 'es'], ['name', 'description']);

        $this->assertSame(['en' => ['name' => 'Fried pork'], 'es' => ['name' => 'Cerdo frito']], $propre);
    }

    public function test_blank_entries_disappear_rather_than_being_stored_as_empty_strings(): void
    {
        $soumis = ['en' => ['name' => '   ', 'description' => 'Something']];

        $propre = Translatable::sanitize($soumis, ['en'], ['name', 'description']);

        $this->assertSame(['en' => ['description' => 'Something']], $propre);
    }

    public function test_clearing_every_translation_stores_null_not_an_empty_array(): void
    {
        // Une colonne vraiment vide, pas un `{}` qui traînerait pour toujours —
        // et qui compterait comme « traduit » dans un rapport plus tard.
        $this->assertNull(Translatable::sanitize(['en' => ['name' => '  ']], ['en'], ['name']));
        $this->assertNull(Translatable::sanitize([], ['en'], ['name']));
        $this->assertNull(Translatable::sanitize('pas un tableau', ['en'], ['name']));
        $this->assertNull(Translatable::sanitize(null, ['en'], ['name']));
    }

    public function test_a_non_scalar_value_is_dropped_not_cast_into_a_broken_string(): void
    {
        // Une tentative de glisser un tableau là où on attend du texte ne doit
        // ni planter, ni produire "Array" en base.
        $soumis = ['en' => ['name' => ['inattendu']]];

        $this->assertNull(Translatable::sanitize($soumis, ['en'], ['name']));
    }

    /* ==================================================================
       locales() — pour le badge « déjà traduit dans »
       ================================================================== */

    public function test_locales_lists_only_languages_actually_carrying_a_translation(): void
    {
        $this->assertSame(['en', 'es'], Translatable::locales(['en' => ['name' => 'x'], 'es' => ['name' => 'y']]));
        $this->assertSame([], Translatable::locales(null));
        $this->assertSame([], Translatable::locales([]));
    }
}
