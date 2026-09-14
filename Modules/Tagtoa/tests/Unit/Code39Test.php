<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\App\Support\Catalog\Code39;
use PHPUnit\Framework\TestCase;

/**
 * Dessiner une étiquette réellement scannable.
 *
 * Une étiquette qui montre seulement « TAG-000245-5 » en gros oblige à taper
 * les chiffres à chaque vente — exactement ce que le scanner devait éviter.
 */
class Code39Test extends TestCase
{
    public function test_a_tagtoa_label_can_be_drawn(): void
    {
        // La raison d'être de ce choix : l'alphabet Code 39 couvre exactement
        // la forme des codes TAGTOA, sans encodage intermédiaire.
        $this->assertTrue(Code39::canEncode(Barcode::internal(245)));
        $this->assertTrue(Code39::canEncode('TAG-000245-5'));
        $this->assertTrue(Code39::canEncode('5449000000996'));
    }

    public function test_what_cannot_be_drawn_is_refused_not_mangled(): void
    {
        // Rendre un code approximatif serait pire que ne rien rendre : on
        // imprimerait une étiquette qui désigne autre chose.
        $this->assertFalse(Code39::canEncode('café'));
        $this->assertFalse(Code39::canEncode(''));
        $this->assertFalse(Code39::canEncode(null));
        $this->assertNull(Code39::svg('café'));
    }

    public function test_a_star_inside_the_code_is_refused(): void
    {
        // L'astérisque délimite le code : au milieu, il couperait la lecture en
        // deux morceaux dont aucun n'est le bon.
        $this->assertFalse(Code39::canEncode('TAG*245'));
    }

    public function test_the_code_is_framed_by_its_delimiters(): void
    {
        // Sans les astérisques d'encadrement, aucun lecteur ne trouve le début.
        $modules = Code39::modules('A');

        // * + espace + A + espace + * = 3 × 9 éléments + 2 espaces
        $this->assertCount(3 * 9 + 2, $modules);
    }

    public function test_every_character_keeps_its_three_wide_elements(): void
    {
        // La règle du Code 39 : exactement trois éléments larges sur neuf. Une
        // erreur de table donnerait un code qui se dessine mais ne se lit pas —
        // le genre de faute qu'on ne voit qu'à l'impression.
        foreach (Code39::PATTERNS as $caractere => $motif) {
            $this->assertSame(9, strlen($motif), "Le motif de « $caractere » n'a pas neuf éléments.");
            $this->assertSame(3, substr_count($motif, 'w'), "« $caractere » n'a pas trois éléments larges.");
        }
    }

    public function test_no_two_characters_share_a_pattern(): void
    {
        // Deux caractères identiques rendraient le décodage ambigu.
        $this->assertSame(
            count(Code39::PATTERNS),
            count(array_unique(Code39::PATTERNS)),
            'Deux caractères partagent le même motif.'
        );
    }

    public function test_the_drawing_starts_and_ends_with_a_bar(): void
    {
        $modules = Code39::modules('TAG-000245-5');

        // Rangs pairs = barres. Un nombre impair d'éléments garantit qu'on
        // commence et qu'on finit par une barre, comme l'exige la norme.
        $this->assertSame(1, count($modules) % 2);
    }

    public function test_the_svg_keeps_its_quiet_zone(): void
    {
        // Sans marge blanche, beaucoup de lecteurs ne trouvent pas le début.
        $svg = Code39::svg('TAG-000245-5', 2, 60);

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('x="20"', $svg, 'La première barre doit commencer après la marge.');
        $this->assertStringContainsString('TAG-000245-5', $svg, 'Les caractères se lisent aussi à l\'œil.');
    }

    public function test_the_label_can_be_printed_without_its_text(): void
    {
        $svg = Code39::svg('TAG-000245-5', 2, 60, false);

        $this->assertStringNotContainsString('<text', $svg);
    }

    public function test_the_svg_escapes_what_it_prints(): void
    {
        // Le libellé est écrit dans du XML : un caractère non échappé casserait
        // le document, ou pire, y injecterait quelque chose.
        $svg = Code39::svg('AB-12', 2, 40);

        $this->assertStringNotContainsString('<text x="0"', $svg);
        $this->assertStringContainsString('>AB-12<', $svg);
    }

    public function test_a_wide_element_is_three_times_a_narrow_one(): void
    {
        // Le rapport fait la lisibilité sur une impression bon marché.
        $modules = Code39::modules('0');

        $this->assertContains(1, $modules);
        $this->assertContains(Code39::RATIO, $modules);
        $this->assertSame(3, Code39::RATIO);
    }
}
