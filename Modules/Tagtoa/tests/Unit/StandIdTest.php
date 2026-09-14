<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Stand\StandId;
use PHPUnit\Framework\TestCase;

/**
 * L'identité imprimée et le secret qui prouve la possession.
 *
 * Ce que ces tests protègent est IRRÉVERSIBLE : le format part chez
 * l'imprimeur, et dix mille objets en circulation ne se rappellent pas.
 */
class StandIdTest extends TestCase
{
    /* ------------------------------------------------------------------
       L'identifiant public.
       ------------------------------------------------------------------ */

    public function test_the_printed_identifier_has_the_agreed_shape(): void
    {
        $this->assertSame('TG-000001', StandId::format(1));
        $this->assertSame('TG-004217', StandId::format(4217));
        $this->assertSame('TG-999999', StandId::format(999999));
    }

    public function test_the_serial_reads_back_from_what_is_printed(): void
    {
        foreach ([1, 42, 4217, 999999] as $serial) {
            $this->assertSame($serial, StandId::serial(StandId::format($serial)));
        }
    }

    public function test_a_merchant_who_retypes_it_badly_is_still_understood(): void
    {
        // « tg 000001 » recopié à la main désigne bien ce stand : refuser
        // reviendrait à lui dire que son propre numéro n'existe pas.
        $this->assertSame(1, StandId::serial('tg-000001'));
        $this->assertSame(1, StandId::serial('  TG-000001  '));
        $this->assertSame(1, StandId::serial('TG 000001'));
        $this->assertSame(1, StandId::serial('TG000001'), 'Le tiret oublié ne doit pas perdre le stand.');
    }

    public function test_what_is_not_an_identifier_designates_nothing(): void
    {
        $this->assertNull(StandId::serial('TG-1'));        // trop court
        $this->assertNull(StandId::serial('000001'));      // sans préfixe
        $this->assertNull(StandId::serial('TG-000000'));   // le zéro n'existe pas
        $this->assertNull(StandId::serial(''));
        $this->assertNull(StandId::serial(null));
        $this->assertFalse(StandId::isValidId('TG-ABCDEF'));
    }

    public function test_the_prefix_can_be_read_back(): void
    {
        // Un lot fabriqué pour un partenaire pourra porter un autre préfixe.
        $this->assertSame('TG', StandId::prefixOf('TG-000001'));
        $this->assertSame('HT', StandId::prefixOf(StandId::format(1, 'HT')));
        $this->assertNull(StandId::prefixOf('pas un identifiant'));
    }

    /* ------------------------------------------------------------------
       L'alphabet : ce qui évite qu'un client abandonne.
       ------------------------------------------------------------------ */

    public function test_the_alphabet_drops_every_ambiguous_character(): void
    {
        // I/1, L/1, O/0 sont LA faute de saisie sur un téléphone, dans un
        // restaurant mal éclairé.
        foreach (['I', 'L', 'O', 'U'] as $lettre) {
            $this->assertStringNotContainsString($lettre, StandId::ALPHABET,
                "« $lettre » est ambigu à l'impression et ne doit pas être tirable.");
        }

        $this->assertSame(32, strlen(StandId::ALPHABET));
        $this->assertSame(32, strlen(count_chars(StandId::ALPHABET, 3)),
            'Un caractère en double fausserait le tirage.');
    }

    public function test_a_misread_character_is_corrected_not_refused(): void
    {
        // Quelqu'un qui lit « O » sur son étiquette a vu un zéro. Le refuser
        // lui ferait croire que son code est faux alors qu'il est juste.
        $this->assertSame('0123', StandId::normalizeSecret('O123'));
        $this->assertSame('1123', StandId::normalizeSecret('I123'));
        $this->assertSame('1123', StandId::normalizeSecret('L123'));
        $this->assertSame('V123', StandId::normalizeSecret('U123'));
    }

    public function test_the_separators_people_add_are_ignored(): void
    {
        $this->assertSame('A3F9K2MP', StandId::normalizeSecret('A3F9-K2MP'));
        $this->assertSame('A3F9K2MP', StandId::normalizeSecret('a3f9 k2mp'));
        $this->assertSame('A3F9K2MP', StandId::normalizeSecret(' A3F9_K2MP '));
    }

    public function test_the_printed_secret_is_grouped_to_be_copied(): void
    {
        // Huit caractères d'affilée se recopient mal ; en deux groupes de
        // quatre, l'œil ne perd pas sa place.
        $this->assertSame('A3F9-K2MP', StandId::pretty('A3F9K2MP'));
        $this->assertSame('A3F9K2MP', StandId::normalizeSecret(StandId::pretty('A3F9K2MP')),
            'Le groupement est cosmétique : il doit disparaître à la comparaison.');
    }

    /* ------------------------------------------------------------------
       Le secret : tout le modèle de sécurité tient ici.
       ------------------------------------------------------------------ */

    public function test_a_secret_only_ever_uses_the_safe_alphabet(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $secret = StandId::makeSecret();

            $this->assertSame(StandId::SECRET_LENGTH, strlen($secret));
            $this->assertSame('', preg_replace('/['.StandId::ALPHABET.']/', '', $secret),
                "Le secret « $secret » contient un caractère hors alphabet.");
        }
    }

    public function test_secrets_do_not_repeat_themselves(): void
    {
        // Deux stands qui partagent un secret, c'est un commerce qui peut
        // réclamer le stand d'un autre.
        $vus = [];
        for ($i = 0; $i < 5000; $i++) {
            $vus[StandId::makeSecret()] = true;
        }

        // 5 000 tirages sur 1,1 × 10¹² : une collision serait le signe d'un
        // générateur cassé, pas de malchance.
        $this->assertCount(5000, $vus);
    }

    public function test_the_secret_keeps_enough_entropy_to_matter(): void
    {
        // Garde : si quelqu'un raccourcit le secret ou réduit l'alphabet
        // « pour simplifier la saisie », la sécurité s'effondre sans qu'aucune
        // fonctionnalité ne cesse de marcher.
        $this->assertGreaterThanOrEqual(40.0, StandId::entropyBits(),
            'Sous 40 bits, un balayage devient réaliste.');
    }

    public function test_every_position_of_the_secret_is_actually_used(): void
    {
        // Un générateur qui ne tirerait que le début de l'alphabet passerait
        // tous les autres tests en divisant l'entropie réelle.
        $caracteres = [];
        for ($i = 0; $i < 2000; $i++) {
            foreach (str_split(StandId::makeSecret()) as $c) {
                $caracteres[$c] = true;
            }
        }

        $this->assertCount(32, $caracteres, "Tout l'alphabet doit être tirable.");
    }

    public function test_a_secret_of_the_wrong_length_is_refused(): void
    {
        $this->assertTrue(StandId::isValidSecret('A3F9K2MP'));
        $this->assertTrue(StandId::isValidSecret('a3f9-k2mp'));
        $this->assertFalse(StandId::isValidSecret('A3F9K2M'));   // sept
        $this->assertFalse(StandId::isValidSecret('A3F9K2MPQ')); // neuf
        $this->assertFalse(StandId::isValidSecret(''));
        $this->assertFalse(StandId::isValidSecret(null));
    }

    /* ------------------------------------------------------------------
       L'URL gravée.
       ------------------------------------------------------------------ */

    public function test_the_engraved_path_carries_nothing_that_can_change(): void
    {
        $chemin = StandId::path('TG-000001');

        $this->assertSame('/s/TG-000001', $chemin);

        // Ni commerce, ni table, ni module : tout cela changera, et le QR non.
        foreach (['menu', 'business', 'table', 'pos'] as $mot) {
            $this->assertStringNotContainsString($mot, $chemin);
        }
    }

    public function test_the_printed_url_stays_short_enough_to_scan_from_afar(): void
    {
        // 30 caractères tiennent en QR version 2 avec correction haute : de gros
        // modules, lisibles de loin et de biais. Au-delà on passe en version 4,
        // le client doit approcher son téléphone, et une partie renonce.
        $url = 'https://tagtoa.com'.StandId::path(StandId::format(999999));

        $this->assertLessThanOrEqual(32, strlen($url), "L'URL imprimée est trop longue : le QR devient dense.");
    }
}
