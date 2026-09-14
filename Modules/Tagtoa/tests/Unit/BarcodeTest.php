<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Catalog\Barcode;
use PHPUnit\Framework\TestCase;

/**
 * Lire, vérifier et fabriquer un code d'article.
 *
 * Un scanner se trompe : mauvaise lumière, code abîmé, téléphone bon marché.
 * Le chiffre de contrôle empêche une lecture erronée de désigner un autre
 * article — donc d'encaisser le mauvais prix.
 */
class BarcodeTest extends TestCase
{
    public function test_real_barcodes_from_real_shelves_are_recognised(): void
    {
        // Codes authentiques, chiffre de contrôle inclus.
        $this->assertTrue(Barcode::hasValidCheckDigit('5449000000996'));  // Coca-Cola, EAN-13
        $this->assertTrue(Barcode::hasValidCheckDigit('4006381333931'));  // EAN-13
        $this->assertTrue(Barcode::hasValidCheckDigit('96385074'));       // EAN-8
        $this->assertTrue(Barcode::hasValidCheckDigit('036000291452'));   // UPC-A
    }

    public function test_a_misread_digit_is_caught(): void
    {
        // C'est tout l'intérêt du chiffre de contrôle : un seul chiffre changé
        // ne doit pas passer pour un code valide.
        $this->assertFalse(Barcode::hasValidCheckDigit('5449000000997'));
        $this->assertFalse(Barcode::hasValidCheckDigit('5449000000986'));
        $this->assertFalse(Barcode::hasValidCheckDigit('96385075'));
    }

    public function test_the_format_is_named_by_its_length(): void
    {
        $this->assertSame(Barcode::TYPE_EAN13, Barcode::typeOf('5449000000996'));
        $this->assertSame(Barcode::TYPE_UPCA, Barcode::typeOf('036000291452'));
        $this->assertSame(Barcode::TYPE_EAN8, Barcode::typeOf('96385074'));
        $this->assertSame(Barcode::TYPE_OTHER, Barcode::typeOf('ABC-123-XYZ'));
        $this->assertNull(Barcode::typeOf(''));
    }

    public function test_a_code_read_with_spaces_or_lowercase_still_works(): void
    {
        // Saisie à la main, lecteur qui ajoute un espace : on nettoie.
        $this->assertSame('5449000000996', Barcode::normalize(' 5449 0000 00996 '));
        $this->assertSame('TAG-000245-5', Barcode::normalize('tag-000245-5'));
    }

    public function test_an_industrial_code_with_a_wrong_check_digit_is_refused(): void
    {
        // L'accepter créerait un article que personne ne retrouvera en scannant.
        $this->assertFalse(Barcode::isAcceptable('5449000000997'));
        $this->assertTrue(Barcode::isAcceptable('5449000000996'));
    }

    public function test_formats_without_a_check_digit_are_accepted_as_they_are(): void
    {
        // Code 128 / Code 39 n'en ont pas : on ne peut rien vérifier, donc on
        // ne refuse rien.
        $this->assertTrue(Barcode::isAcceptable('ABC-123'));
        $this->assertTrue(Barcode::isAcceptable('PROD-2026-XY'));
    }

    public function test_something_too_short_to_be_a_code_is_refused(): void
    {
        foreach (['', ' ', 'A', '12', '123', null] as $trop_court) {
            $this->assertFalse(Barcode::isAcceptable($trop_court));
        }
    }

    /* ------------------------------------------------------------------
       Produits locaux sans code-barres : pâté, fresco, sachet dlo.
       C'est la majorité de ce que vend une boutique de quartier.
       ------------------------------------------------------------------ */

    public function test_a_local_product_gets_a_code_it_can_print(): void
    {
        $code = Barcode::internal(245);

        $this->assertSame('TAG-000245-5', $code);
        $this->assertSame(Barcode::TYPE_INTERNAL, Barcode::typeOf($code));
        $this->assertTrue(Barcode::isAcceptable($code));
    }

    public function test_an_internal_code_leads_back_to_its_article(): void
    {
        foreach ([1, 42, 245, 999999] as $id) {
            $this->assertSame($id, Barcode::internalProductId(Barcode::internal($id)),
                "Le code de l'article $id doit ramener à lui.");
        }
    }

    public function test_a_damaged_label_is_rejected_rather_than_guessed(): void
    {
        // Étiquette froissée, chiffre mal lu : mieux vaut refuser que de
        // désigner un autre article et encaisser le mauvais prix.
        $this->assertNull(Barcode::internalProductId('TAG-000245-8'));
        $this->assertNull(Barcode::internalProductId('TAG-00245-7'));
        $this->assertNull(Barcode::internalProductId('TAG-000245'));
        $this->assertNull(Barcode::internalProductId('5449000000996'));
        $this->assertNull(Barcode::internalProductId(null));
    }

    public function test_an_internal_code_stays_readable_by_eye(): void
    {
        // Quand le scanner refuse, le caissier tape les chiffres : le code doit
        // donc rester court et sans caractère ambigu.
        $code = Barcode::internal(245);

        $this->assertLessThanOrEqual(14, strlen($code));
        $this->assertMatchesRegularExpression('/^TAG-\d{6}-\d$/', $code);
    }

    public function test_the_check_digit_formula_holds_for_every_length(): void
    {
        // La pondération part de la DROITE : c'est ce qui rend la même formule
        // valable pour EAN-8, UPC-A et EAN-13.
        $this->assertSame(6, Barcode::checkDigit('544900000099'));
        $this->assertSame(4, Barcode::checkDigit('9638507'));
        $this->assertSame(2, Barcode::checkDigit('03600029145'));
        $this->assertNull(Barcode::checkDigit(''));
        $this->assertNull(Barcode::checkDigit('12A4'));
    }

    public function test_an_absurdly_long_code_is_cut_rather_than_stored_whole(): void
    {
        $long = str_repeat('9', 200);

        $this->assertSame(Barcode::MAX_LENGTH, strlen(Barcode::normalize($long)));
    }
}
