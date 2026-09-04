<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Menu\BusinessProfile;
use PHPUnit\Framework\TestCase;

/**
 * Le formulaire MENU s'adapte au type d'établissement.
 *
 * L'enjeu de sécurité est le même que pour les moyens de paiement : ce qui
 * arrive du formulaire est indexé par clé, et TOUT ce qui n'est pas déclaré
 * pour ce type est écarté AVANT d'atteindre la base.
 */
class BusinessProfileTest extends TestCase
{
    public function test_a_hotel_speaks_of_rooms_and_nights_not_of_dishes(): void
    {
        $hotel = BusinessProfile::for('hotel');

        $this->assertSame('Chambre', $hotel['noun']);
        $this->assertSame('Prix par nuit', $hotel['price_hint']);
        $this->assertArrayHasKey('capacity', $hotel['fields']);
        $this->assertArrayHasKey('amenities', $hotel['fields']);

        // …et un restaurant ne parle pas de capacité de chambre.
        $this->assertArrayNotHasKey('capacity', BusinessProfile::fields('restaurant'));
        $this->assertArrayHasKey('prep_time', BusinessProfile::fields('restaurant'));
    }

    public function test_an_unknown_type_falls_back_instead_of_crashing(): void
    {
        $this->assertSame(BusinessProfile::PROFILES['other'], BusinessProfile::for('spatioport'));
        $this->assertSame(BusinessProfile::PROFILES['other'], BusinessProfile::for(null));
    }

    public function test_a_field_not_declared_for_this_type_never_reaches_the_database(): void
    {
        $clean = BusinessProfile::sanitize('restaurant', [
            'prep_time' => 25,
            'capacity'  => 4,           // champ d'hôtel : hors sujet ici
            'is_admin'  => true,        // clé inventée
        ]);

        $this->assertSame(['prep_time' => 25], $clean);
    }

    public function test_a_choice_outside_the_catalogue_is_refused(): void
    {
        $clean = BusinessProfile::sanitize('hotel', [
            'room_type' => 'Château',              // hors catalogue
            'view'      => 'Mer',                  // valide
            'amenities' => ['Wi-Fi', 'Héliport'],  // un valide, un inventé
        ]);

        $this->assertSame(['view' => 'Mer', 'amenities' => ['Wi-Fi']], $clean);
    }

    public function test_numbers_are_clamped_to_their_range_and_stay_whole_when_whole(): void
    {
        $clean = BusinessProfile::sanitize('hotel', ['capacity' => 999, 'area' => 24.5, 'beds' => -3]);

        $this->assertSame(30, $clean['capacity']);   // borné au maximum du champ
        $this->assertSame(24.5, $clean['area']);     // décimal conservé
        $this->assertSame(0, $clean['beds']);        // borné au minimum
        $this->assertIsInt($clean['capacity']);      // « 30 personnes », pas « 30.0 »
    }

    public function test_empty_input_stores_nothing_rather_than_an_empty_object(): void
    {
        $this->assertNull(BusinessProfile::sanitize('hotel', []));
        $this->assertNull(BusinessProfile::sanitize('hotel', ['capacity' => '', 'view' => null]));
        $this->assertNull(BusinessProfile::sanitize('hotel', 'pas un tableau'));
    }

    public function test_an_unchecked_box_is_not_stored(): void
    {
        $this->assertNull(BusinessProfile::sanitize('hotel', ['breakfast' => '0']));
        $this->assertSame(['breakfast' => true], BusinessProfile::sanitize('hotel', ['breakfast' => '1']));
    }

    public function test_display_reads_like_a_human_wrote_it(): void
    {
        $rows = BusinessProfile::display('hotel', [
            'capacity'  => 4,
            'amenities' => ['Climatisation', 'Wi-Fi'],
            'breakfast' => true,
            'view'      => 'Mer',
        ]);

        $this->assertSame([
            ['label' => 'Capacité',            'value' => '4 personnes'],
            ['label' => 'Vue',                 'value' => 'Mer'],
            ['label' => 'Équipements',         'value' => 'Climatisation, Wi-Fi'],
            ['label' => 'Petit-déjeuner inclus', 'value' => 'Oui'],
        ], $rows);
    }

    public function test_attributes_left_over_from_another_type_are_not_shown_raw(): void
    {
        // Le marchand est passé d'« Hôtel » à « Restaurant » : les anciennes
        // valeurs restent en base mais ne doivent pas s'afficher n'importe comment.
        $rows = BusinessProfile::display('restaurant', ['capacity' => 4, 'prep_time' => 15]);

        $this->assertSame([['label' => 'Temps de préparation', 'value' => '15 min']], $rows);
    }

    public function test_every_declared_field_is_usable_by_the_form_and_the_validator(): void
    {
        $known = [BusinessProfile::T_TEXT, BusinessProfile::T_NUMBER,
            BusinessProfile::T_SELECT, BusinessProfile::T_TAGS, BusinessProfile::T_BOOL];

        foreach (BusinessProfile::PROFILES as $type => $profile) {
            $this->assertNotEmpty($profile['noun'], "$type sans nom d'article.");
            $this->assertNotEmpty($profile['categories'], "$type sans catégories proposées.");

            foreach ($profile['fields'] as $key => $spec) {
                $this->assertContains($spec['type'], $known, "$type.$key : type de champ inconnu.");
                $this->assertNotEmpty($spec['label'], "$type.$key sans libellé.");

                if (in_array($spec['type'], [BusinessProfile::T_SELECT, BusinessProfile::T_TAGS], true)) {
                    $this->assertNotEmpty($spec['options'] ?? [], "$type.$key : liste de choix vide.");
                }
            }
        }
    }
}
