<?php

namespace Modules\Tagtoa\Tests\Unit;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — `tagtoa_sites.hours` existait déjà en JSON libre
| `[{day,value}]` : bon pour de l'affichage, impossible à évaluer côté
| serveur. BusinessHours répond à « est-il ouvert MAINTENANT ? », ce qui
| permet de REFUSER une commande hors horaires, pas seulement de l'afficher.
|--------------------------------------------------------------------------
*/

use Modules\Tagtoa\App\Support\Menu\BusinessHours;
use PHPUnit\Framework\TestCase;

class BusinessHoursTest extends TestCase
{
    private function heure(string $jour, string $hhmm): \DateTimeImmutable
    {
        // Base connue : 2026-09-14 est un lundi.
        $offsets = ['mon' => 0, 'tue' => 1, 'wed' => 2, 'thu' => 3, 'fri' => 4, 'sat' => 5, 'sun' => 6];
        $date = (new \DateTimeImmutable('2026-09-14'))->modify('+'.$offsets[$jour].' days');

        return $date->setTime((int) substr($hhmm, 0, 2), (int) substr($hhmm, 3, 2));
    }

    public function test_no_hours_configured_means_always_open(): void
    {
        $this->assertTrue(BusinessHours::isOpenAt(null, $this->heure('mon', '03:00')));
        $this->assertTrue(BusinessHours::isOpenAt([], $this->heure('sun', '23:59')));
    }

    public function test_a_simple_daytime_range_is_respected(): void
    {
        $hours = ['mon' => ['open' => '08:00', 'close' => '20:00']];

        $this->assertTrue(BusinessHours::isOpenAt($hours, $this->heure('mon', '08:00')));
        $this->assertTrue(BusinessHours::isOpenAt($hours, $this->heure('mon', '19:59')));
        $this->assertFalse(BusinessHours::isOpenAt($hours, $this->heure('mon', '20:00')));
        $this->assertFalse(BusinessHours::isOpenAt($hours, $this->heure('mon', '07:59')));
    }

    public function test_a_day_absent_from_a_configured_week_is_closed(): void
    {
        // Le lundi est renseigné, le mardi ne l'est pas : mardi est FERMÉ,
        // pas « ouvert par défaut » — sinon un seul jour renseigné rendrait
        // le commerce ouvert 24/7 le reste de la semaine.
        $hours = ['mon' => ['open' => '08:00', 'close' => '20:00']];

        $this->assertFalse(BusinessHours::isOpenAt($hours, $this->heure('tue', '12:00')));
    }

    public function test_a_range_crossing_midnight_is_treated_as_overnight(): void
    {
        // Un bar/club cité explicitement par le fondateur : 18h00 à 02h00.
        $hours = ['fri' => ['open' => '18:00', 'close' => '02:00']];

        $this->assertTrue(BusinessHours::isOpenAt($hours, $this->heure('fri', '23:30')));
        $this->assertTrue(BusinessHours::isOpenAt($hours, $this->heure('fri', '01:59')));
        $this->assertFalse(BusinessHours::isOpenAt($hours, $this->heure('fri', '02:00')));
        $this->assertFalse(BusinessHours::isOpenAt($hours, $this->heure('fri', '17:59')));
    }

    public function test_sanitize_drops_a_day_marked_closed(): void
    {
        $out = BusinessHours::sanitize([
            'mon' => ['open' => '08:00', 'close' => '20:00'],
            'tue' => ['closed' => '1', 'open' => '08:00', 'close' => '20:00'],
        ]);

        $this->assertSame(['open' => '08:00', 'close' => '20:00'], $out['mon']);
        $this->assertNull($out['tue']);
    }

    public function test_sanitize_drops_a_half_filled_range_rather_than_guessing(): void
    {
        // Un seul jour envoyé, à moitié rempli : aucun jour de la semaine
        // n'a d'horaire valide, donc sanitize() renvoie null pour TOUT —
        // pas un objet qui prétendrait que « mon » est fermé.
        $this->assertNull(BusinessHours::sanitize(['mon' => ['open' => '08:00', 'close' => '']]));

        // Aux côtés d'un autre jour valide, seul le jour à moitié rempli
        // retombe à null : le reste de la semaine n'est pas perdu.
        $out = BusinessHours::sanitize([
            'mon' => ['open' => '08:00', 'close' => ''],
            'tue' => ['open' => '08:00', 'close' => '20:00'],
        ]);
        $this->assertNull($out['mon']);
        $this->assertSame(['open' => '08:00', 'close' => '20:00'], $out['tue']);
    }

    public function test_sanitize_rejects_a_malformed_time(): void
    {
        $this->assertNull(BusinessHours::sanitize(['mon' => ['open' => '25:00', 'close' => '20:00']]));

        $out = BusinessHours::sanitize([
            'mon' => ['open' => '25:00', 'close' => '20:00'],
            'tue' => ['open' => '08:00', 'close' => '20:00'],
        ]);
        $this->assertNull($out['mon']);
    }

    public function test_sanitize_returns_null_when_every_day_is_empty(): void
    {
        // Un formulaire jamais touché ne doit pas écrire un JSON vide qui ne
        // dit rien — null évite d'écraser un « toujours ouvert » implicite.
        $this->assertNull(BusinessHours::sanitize([
            'mon' => ['closed' => '1'], 'tue' => null,
        ]));
        $this->assertNull(BusinessHours::sanitize('not-an-array'));
        $this->assertNull(BusinessHours::sanitize(null));
    }

    public function test_range_label_reads_fermé_or_the_hours(): void
    {
        $hours = ['mon' => ['open' => '08:00', 'close' => '20:00']];

        $this->assertSame('08:00 – 20:00', BusinessHours::rangeLabel($hours, 'mon'));
        $this->assertSame('Fermé', BusinessHours::rangeLabel($hours, 'tue'));
        $this->assertSame('Fermé', BusinessHours::rangeLabel(null, 'mon'));
    }
}
