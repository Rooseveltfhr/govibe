<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\TransferCode;
use PHPUnit\Framework\TestCase;

/**
 * Le code d'une cession — logique pure, sans base de données.
 *
 * Il ne ressemble PAS au secret gratté, et c'est délibéré : le secret prouve
 * qu'on tient l'objet, ce code-ci prouve que le propriétaire a voulu céder.
 * Deux autorités différentes.
 */
class TransferCodeTest extends TestCase
{
    public function test_a_code_has_the_expected_shape(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $c = TransferCode::make();
            $this->assertSame(TransferCode::LENGTH, strlen($c));
            $this->assertMatchesRegularExpression('/^['.StandId::ALPHABET.']+$/', $c);
            $this->assertTrue(TransferCode::isValid($c));
        }
    }

    public function test_two_codes_are_never_the_same(): void
    {
        $vus = [];
        for ($i = 0; $i < 500; $i++) {
            $vus[TransferCode::make()] = true;
        }
        $this->assertCount(500, $vus, 'Générateur prévisible ou trop court.');
    }

    public function test_a_human_can_mistype_it_and_still_be_understood(): void
    {
        // Ce code se recopie depuis WhatsApp, parfois à la main, parfois dans
        // un restaurant mal éclairé. Refuser « O » là où l'œil a lu un zéro
        // ferait croire au repreneur que son code est faux alors qu'il est juste.
        $this->assertSame('0123456789AB', TransferCode::normalize('o123-4567-89ab'));
        $this->assertSame('1111VVVV0000', TransferCode::normalize('iLl1 vuVu OO00'));
        $this->assertSame('A3F9K2MP7XQR', TransferCode::normalize('  a3f9.k2mp_7xqr  '));
    }

    public function test_it_is_shown_in_groups_of_four(): void
    {
        // Douze caractères d'affilée se recopient mal ; en trois groupes,
        // l'œil ne perd pas sa place.
        $this->assertSame('A3F9-K2MP-7XQR', TransferCode::pretty('a3f9k2mp7xqr'));

        // Le groupement est cosmétique : la comparaison passe par la
        // normalisation, qui retire les tirets.
        $this->assertSame(
            TransferCode::fingerprint('A3F9K2MP7XQR'),
            TransferCode::fingerprint('a3f9-k2mp-7xqr')
        );
    }

    public function test_a_malformed_code_is_refused(): void
    {
        $this->assertFalse(TransferCode::isValid(null));
        $this->assertFalse(TransferCode::isValid(''));
        $this->assertFalse(TransferCode::isValid('A3F9K2MP'));      // longueur du secret gratté
        $this->assertFalse(TransferCode::isValid('!!!!!!!!!!!!'));  // hors alphabet
    }

    public function test_the_fingerprint_is_what_goes_to_the_database(): void
    {
        $c = 'A3F9K2MP7XQR';

        $this->assertSame(hash('sha256', $c), TransferCode::fingerprint($c));
        $this->assertSame(64, strlen(TransferCode::fingerprint($c)));
        $this->assertNotSame($c, TransferCode::fingerprint($c));
    }

    public function test_the_code_stays_long_enough_to_be_unguessable(): void
    {
        // GARDE. Si quelqu'un raccourcit le code « pour faciliter la saisie »,
        // la sécurité s'effondre sans qu'aucune fonctionnalité ne cesse de
        // marcher — exactement la régression qu'on ne voit jamais.
        //
        // Ce code circule sur WhatsApp et se présente sans qu'on sache d'avance
        // à quelle offre il appartient : on ne peut donc pas compter les essais
        // « par offre » avant de l'avoir reconnu. La marge est sur la longueur.
        $this->assertGreaterThanOrEqual(55, TransferCode::entropyBits(),
            'Un code de cession plus court se devine.');

        $this->assertGreaterThan(StandId::entropyBits(), TransferCode::entropyBits(),
            'Le code de cession doit être plus long que le secret gratté, qui lui '.
            'est protégé par une limite de dix essais par stand.');
    }
}
