<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Menu\CategoryIcon;
use PHPUnit\Framework\TestCase;

/**
 * L'icône d'une catégorie de menu, déduite de son nom.
 *
 * Les catégories portaient un emoji saisi à la main : il se dessine autrement
 * sur chaque téléphone, tombe en carré blanc sur beaucoup d'Android bon marché,
 * et presque aucun marchand n'en met un — si bien que la barre était nue.
 */
class CategoryIconTest extends TestCase
{
    public function test_it_reads_the_french_words_a_merchant_actually_writes(): void
    {
        foreach ([
            'Entrées'           => 'fa-bowl-food',
            'Plats principaux'  => 'fa-utensils',
            'Boissons'          => 'fa-mug-hot',
            'Desserts'          => 'fa-ice-cream',
            'Poissons grillés'  => 'fa-fish',
            'Jus naturels'      => 'fa-glass-water',
            'Chambres'          => 'fa-bed',
            'Promo du jour'     => 'fa-tags',
        ] as $nom => $attendu) {
            $this->assertSame($attendu, CategoryIcon::deduire($nom), "« $nom »");
        }
    }

    public function test_it_reads_creole_too(): void
    {
        // C'est ainsi qu'un marchand haïtien écrit réellement sa carte. Ne
        // chercher qu'en français le renverrait à l'icône par défaut.
        $this->assertSame('fa-mug-hot', CategoryIcon::deduire('Bwason'));
        $this->assertSame('fa-bowl-rice', CategoryIcon::deduire('Diri ak sòs'));
        $this->assertSame('fa-fish', CategoryIcon::deduire('Pwason fri'));
        $this->assertSame('fa-drumstick-bite', CategoryIcon::deduire('Griot'));
    }

    public function test_accents_and_case_never_change_the_answer(): void
    {
        // « Entrées », « ENTREES » et « entrees » désignent la même chose : sans
        // mise à plat, deux marchands sur trois tomberaient sur l'icône par
        // défaut pour une simple histoire d'accent.
        foreach (['Entrées', 'ENTREES', 'entrees', 'Entrée'] as $n) {
            $this->assertSame('fa-bowl-food', CategoryIcon::deduire($n), "« $n »");
        }
    }

    public function test_a_short_word_needs_a_word_boundary(): void
    {
        // « Other » contient « the ». Sans frontière de mot, une catégorie
        // « Autres » en anglais s'afficherait avec une tasse de thé — le genre
        // de défaut que personne ne signale et que tout le monde voit.
        $this->assertSame(CategoryIcon::DEFAUT, CategoryIcon::deduire('Other'));
        $this->assertSame(CategoryIcon::DEFAUT, CategoryIcon::deduire('Others'));

        // Mais le vrai thé reste du thé.
        $this->assertSame('fa-mug-hot', CategoryIcon::deduire('Thé et infusions'));
    }

    public function test_a_long_word_is_found_inside_a_phrase(): void
    {
        // « Boissons », « Nos desserts maison » : ce ne sont pas des mots
        // isolés. Exiger une frontière partout les manquerait tous.
        $this->assertSame('fa-ice-cream', CategoryIcon::deduire('Nos desserts maison'));
        $this->assertSame('fa-pizza-slice', CategoryIcon::deduire('Pizzas artisanales'));
    }

    public function test_the_first_matching_word_wins_and_the_order_is_deliberate(): void
    {
        // « Jus de fruit » est une boisson, pas un fruit. « Soupe de poisson »
        // est un poisson, pas une soupe. C'est l'ORDRE du tableau qui le dit —
        // ce test échoue si quelqu'un réordonne « pour ranger ».
        $this->assertSame('fa-glass-water', CategoryIcon::deduire('Jus de fruit'));
        $this->assertSame('fa-fish', CategoryIcon::deduire('Soupe de poisson'));
    }

    public function test_an_unknown_name_gets_a_neutral_icon_not_an_error(): void
    {
        // Un marchand écrit ce qu'il veut. Une catégorie sans correspondance
        // doit rester présentable, pas vide.
        foreach (['Sans nom particulier', '', null, '12345', '★'] as $n) {
            $this->assertSame(CategoryIcon::DEFAUT, CategoryIcon::deduire($n));
        }
    }

    public function test_an_explicit_choice_always_wins(): void
    {
        // La déduction n'est qu'un défaut : un marchand qui enregistre sa classe
        // doit la voir, même si le nom dit autre chose.
        $this->assertSame('fa-fish', CategoryIcon::resolve('fa-fish', 'Boissons'));
        $this->assertSame('fa-fish', CategoryIcon::resolve('FA-FISH', 'Boissons'));
    }

    public function test_an_old_emoji_is_ignored_rather_than_printed(): void
    {
        // Les catégories existantes portent des emojis. Les rendre tels quels
        // dans un <i class="fa-solid …"> produirait une classe absurde.
        $this->assertSame('fa-mug-hot', CategoryIcon::resolve('🍹', 'Boissons'));
        $this->assertSame('fa-ice-cream', CategoryIcon::resolve('🍰', 'Desserts'));
    }

    public function test_the_result_is_always_a_usable_font_awesome_class(): void
    {
        // Une valeur qui ne serait pas une classe casserait silencieusement
        // l'affichage : l'icône disparaîtrait sans qu'aucune erreur ne remonte.
        foreach (array_values(CategoryIcon::MOTS) as $icone) {
            $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', $icone);
        }
        $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', CategoryIcon::DEFAUT);
    }

    public function test_an_injected_class_cannot_carry_markup(): void
    {
        // La valeur part dans un attribut class. Une chaîne libre y ferait
        // entrer ce qu'on veut.
        $this->assertSame(CategoryIcon::DEFAUT,
            CategoryIcon::resolve('fa-x" onload="alert(1)', 'Zzz'));
        $this->assertSame(CategoryIcon::DEFAUT,
            CategoryIcon::resolve('<script>', 'Zzz'));
    }
}
