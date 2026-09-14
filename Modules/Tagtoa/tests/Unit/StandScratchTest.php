<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use PHPUnit\Framework\TestCase;

/**
 * Ce qui est imprimé sous le panneau à gratter — et qui part chez l'imprimeur.
 */
class StandScratchTest extends TestCase
{
    public function test_the_payload_carries_identity_and_authority(): void
    {
        $this->assertSame('TG-000041-A3F9K2MP', StandScratch::payload('TG-000041', 'A3F9K2MP'));
    }

    public function test_the_payload_drops_the_print_grouping(): void
    {
        // Le groupement « A3F9-K2MP » aide l'œil humain ; la caméra n'en a pas
        // besoin, et chaque caractère de moins agrandit les modules du QR —
        // donc sa lisibilité sur un panneau gratté à l'ongle.
        $this->assertSame('TG-000041-A3F9K2MP', StandScratch::payload('TG-000041', 'A3F9-K2MP'));
    }

    public function test_a_camera_read_comes_back_in_two_pieces(): void
    {
        $this->assertSame(['TG-000041', 'A3F9K2MP'], StandScratch::parse('TG-000041-A3F9K2MP'));
    }

    public function test_the_separator_survives_the_scanner_filter(): void
    {
        // Le composant caméra ne retient que [A-Z0-9-]. Un deux-points serait
        // avalé EN SILENCE et la charge utile arriverait collée : ce test
        // échouerait si quelqu'un changeait le séparateur « pour la lisibilité ».
        $this->assertMatchesRegularExpression('/^[A-Z0-9\-]+$/',
            StandScratch::payload('TG-000041', 'A3F9K2MP'),
            'Le séparateur choisi ne traverse pas le filtre du scanner.');
    }

    public function test_a_payload_read_by_the_scanner_filter_still_parses(): void
    {
        // On simule exactement ce que fait le composant caméra avant de livrer.
        $brut   = StandScratch::payload('TG-000041', 'A3F9K2MP');
        $filtre = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($brut));

        $this->assertSame(['TG-000041', 'A3F9K2MP'], StandScratch::parse($filtre));
    }

    public function test_it_accepts_what_a_human_actually_types(): void
    {
        foreach ([
            'tg 000041 a3f9-k2mp',
            'TG-000041-A3F9-K2MP',
            'TG000041A3F9K2MP',
            '  TG-000041 : A3F9K2MP  ',
        ] as $saisie) {
            $this->assertSame(['TG-000041', 'A3F9K2MP'], StandScratch::parse($saisie),
                "Saisie refusée à tort : « $saisie »");
        }
    }

    public function test_it_applies_the_crockford_corrections(): void
    {
        // Quelqu'un qui lit « O » sur son étiquette a vu un zéro. Le refuser lui
        // ferait croire que son code est faux alors qu'il est juste.
        [$id, $secret] = StandScratch::parse('TG-000041-A3F9K2MO');

        $this->assertSame('TG-000041', $id);
        $this->assertSame('A3F9K2M0', $secret);
    }

    public function test_the_front_qr_alone_parses_without_a_secret(): void
    {
        // Viser le recto par mégarde est le geste le plus probable de tous :
        // ça ne doit pas être une erreur, juste une lecture incomplète.
        $this->assertSame(['TG-000041', null], StandScratch::parse('https://tagtoa.com/s/TG-000041'));
        $this->assertSame(['TG-000041', null], StandScratch::parse('TG-000041'));

        $this->assertFalse(StandScratch::isComplete('TG-000041'));
        $this->assertTrue(StandScratch::isComplete('TG-000041-A3F9K2MP'));
    }

    public function test_nonsense_comes_back_empty_rather_than_throwing(): void
    {
        // Une caméra lit n'importe quel code-barres qui passe : un code produit,
        // une étiquette de transporteur. Ça ne doit pas lever d'exception.
        foreach (['', null, 'BONJOUR', '12345', 'https://exemple.com/', '---'] as $bruit) {
            $this->assertSame([null, null], StandScratch::parse($bruit));
            $this->assertFalse(StandScratch::isComplete($bruit));
        }
    }

    public function test_a_secret_of_the_wrong_length_is_not_complete(): void
    {
        // Un panneau à moitié gratté donne un secret tronqué. Mieux vaut le dire
        // que d'envoyer au serveur une tentative qu'on sait perdue — et qui
        // consommerait un essai sur les dix de l'heure.
        $this->assertFalse(StandScratch::isComplete('TG-000041-A3F9'));
    }

    public function test_the_trace_never_carries_the_secret(): void
    {
        // Le secret d'un stand NON RÉCLAMÉ vaut le stand lui-même. Un journal
        // qui le contient est une liste de stands à voler.
        $trace = StandScratch::safeTrace('TG-000041-A3F9K2MP');

        $this->assertStringContainsString('TG-000041', $trace);
        $this->assertStringNotContainsString('A3F9', $trace);
        $this->assertStringNotContainsString('K2MP', $trace);
    }

    public function test_the_trace_does_not_merely_truncate_the_secret(): void
    {
        // Tronquer n'est pas masquer : « A3F9… » divise l'espace de recherche
        // par 32⁴ et rend le reste atteignable. Aucun caractère du secret ne
        // doit survivre.
        $this->assertSame('TG-000041-********', StandScratch::safeTrace('TG-000041-A3F9K2MP'));
    }

    public function test_the_payload_stays_short_enough_to_print_small(): void
    {
        // Ce QR est imprimé sous un panneau à gratter de quelques millimètres.
        // Au-delà d'une vingtaine de caractères, la version du QR monte, les
        // modules rétrécissent, et un panneau gratté à l'ongle ne se lit plus.
        $charge = StandScratch::payload(StandId::format(999999), 'A3F9K2MP');

        $this->assertLessThanOrEqual(20, strlen($charge),
            'La charge utile a grossi : le QR sous le panneau deviendra illisible.');
    }
}
