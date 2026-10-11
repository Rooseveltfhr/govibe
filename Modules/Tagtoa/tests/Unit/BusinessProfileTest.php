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

    /** Signalé : le champ « Nom » de l'établissement suggérait « Lounge 509 »
     *  même pour une pharmacie — l'exemple doit suivre le type choisi. */
    public function test_each_type_suggests_a_name_example_that_actually_fits_it(): void
    {
        $this->assertSame('Pharmacie Lakay', BusinessProfile::for('pharmacy')['name_example']);
        $this->assertSame('Hôtel Belle Vue', BusinessProfile::for('hotel')['name_example']);
        $this->assertSame('Lounge 509', BusinessProfile::for('lounge')['name_example']);

        // Chaque exemple appartient à son propre métier — aucun ne devrait se
        // retrouver suggéré pour un type différent.
        $exemples = array_column(BusinessProfile::PROFILES, 'name_example');
        $this->assertSame($exemples, array_unique($exemples), 'Deux métiers partagent le même exemple de nom.');
    }

    /** Signalé : un restaurant se voyait suggérer des rayons de boutique
     *  (« Nettoyage & hygiène », « Cosmétique ») — hors de propos pour un
     *  restaurant. La liste proposée doit être celle du métier, et rien d'autre. */
    public function test_a_restaurant_suggests_only_its_own_curated_categories(): void
    {
        $this->assertSame(
            ['Plats principaux', 'Boisson', 'Alcool', 'Cocktail', 'Dessert', 'Fast-food', 'Salade', 'Nourriture', 'Crèmes', 'Jus naturel', 'Plats du jour'],
            BusinessProfile::for('restaurant')['categories']
        );
    }

    /** Signalé : « Sur table / À emporter / Livraison » se choisissait pour
     *  tout le menu — un plat qui ne voyage pas (un flambé, une soupe très
     *  chaude) doit pouvoir restreindre ses propres modes de service. */
    public function test_a_restaurant_dish_can_declare_its_own_service_modes_and_extra_ingredients(): void
    {
        $restaurant = BusinessProfile::for('restaurant');

        $this->assertSame(['Sur place', 'À emporter', 'Livraison'], $restaurant['fields']['service_modes']['options']);
        $this->assertContains('Fromage', $restaurant['fields']['extras']['options']);
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

    public function test_a_pharmacy_speaks_of_medications_and_prescriptions(): void
    {
        $pharmacie = BusinessProfile::for('pharmacy');

        $this->assertSame('Médicament', $pharmacie['noun']);
        $this->assertArrayHasKey('requires_prescription', $pharmacie['fields']);
        $this->assertArrayHasKey('dosage', $pharmacie['fields']);
    }

    public function test_a_bar_speaks_of_bottles_and_alcohol_by_volume(): void
    {
        $bar = BusinessProfile::for('bar');

        $this->assertArrayHasKey('serving', $bar['fields']);
        $this->assertArrayHasKey('abv', $bar['fields']);
        $this->assertContains('Bouteille', $bar['fields']['serving']['options']);
    }

    public function test_a_boutique_speaks_of_size_and_brand_not_of_dishes(): void
    {
        $boutique = BusinessProfile::for('boutique');

        $this->assertArrayHasKey('size', $boutique['fields']);
        $this->assertArrayHasKey('brand', $boutique['fields']);
        $this->assertArrayNotHasKey('prep_time', $boutique['fields']);
    }

    /** Signalé : une quincaillerie se retrouvait sans son propre type — elle
     *  était simplement une « boutique » parmi d'autres, sans son vocabulaire
     *  ni ses rayons (plomberie, électricité, peinture…). */
    public function test_a_hardware_store_speaks_of_tools_and_pipes_not_of_clothes(): void
    {
        $quincaillerie = BusinessProfile::for('quincaillerie');

        $this->assertArrayHasKey('unit', $quincaillerie['fields']);
        $this->assertArrayHasKey('brand', $quincaillerie['fields']);
        $this->assertArrayNotHasKey('size', $quincaillerie['fields']);
        $this->assertContains('Plomberie', $quincaillerie['categories']);
        $this->assertContains('Électricité', $quincaillerie['categories']);
    }

    /** Autre manque signalé : un salon de beauté/barbershop n'avait pas de
     *  type propre non plus — « Salon » n'apparaissait même pas dans la
     *  liste, seulement dans la description de « Autre ». */
    public function test_a_beauty_salon_speaks_of_appointments_and_hair_not_of_hardware(): void
    {
        $salon = BusinessProfile::for('salon');

        $this->assertSame('Prestation', $salon['noun']);
        $this->assertArrayHasKey('requires_appointment', $salon['fields']);
        $this->assertArrayHasKey('for_whom', $salon['fields']);
        $this->assertContains('Coiffure', $salon['categories']);
        $this->assertContains('Rasage & barbe', $salon['categories']);
        $this->assertArrayNotHasKey('abv', $salon['fields']);
    }

    public function test_a_service_provider_speaks_of_appointments_not_of_dishes(): void
    {
        $service = BusinessProfile::for('service');

        $this->assertSame('Service', $service['noun']);
        $this->assertArrayHasKey('requires_appointment', $service['fields']);
        $this->assertArrayHasKey('location', $service['fields']);
        $this->assertContains('À domicile', $service['fields']['location']['options']);
        $this->assertArrayNotHasKey('prep_time', $service['fields']);
    }

    public function test_every_declared_field_is_usable_by_the_form_and_the_validator(): void
    {
        $known = [BusinessProfile::T_TEXT, BusinessProfile::T_NUMBER,
            BusinessProfile::T_SELECT, BusinessProfile::T_TAGS, BusinessProfile::T_BOOL];

        foreach (BusinessProfile::PROFILES as $type => $profile) {
            $this->assertNotEmpty($profile['noun'], "$type sans nom d'article.");
            $this->assertNotEmpty($profile['categories'], "$type sans catégories proposées.");
            $this->assertArrayHasKey('name_example', $profile, "$type sans exemple de nom d'établissement.");
            $this->assertNotEmpty($profile['name_example'], "$type : exemple de nom vide.");
            $this->assertArrayHasKey('tagline_example', $profile, "$type sans exemple de slogan.");

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
