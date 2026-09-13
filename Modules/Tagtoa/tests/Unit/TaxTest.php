<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Tax\Tax;
use PHPUnit\Framework\TestCase;

/**
 * La taxe sur une vente.
 *
 * Se tromper de convention — taxe comprise ou hors taxe — c'est soit facturer
 * 10 % de trop au client, soit payer la taxe de sa poche à chaque vente.
 */
class TaxTest extends TestCase
{
    /* ------------------------------------------------------------------
       Les deux conventions.
       ------------------------------------------------------------------ */

    public function test_a_tax_included_price_is_what_the_customer_pays(): void
    {
        // L'usage en Haïti : le prix sur l'étiquette EST le prix payé. La taxe
        // s'extrait, elle ne s'ajoute pas.
        $r = Tax::split(110.0, 10.0, true);

        $this->assertSame(110.0, $r['total'], 'Le client paie le prix affiché.');
        $this->assertSame(100.0, $r['base']);
        $this->assertSame(10.0, $r['tax']);
    }

    public function test_a_tax_excluded_price_grows_at_the_till(): void
    {
        $r = Tax::split(100.0, 10.0, false);

        $this->assertSame(100.0, $r['base']);
        $this->assertSame(10.0, $r['tax']);
        $this->assertSame(110.0, $r['total']);
    }

    public function test_the_two_conventions_never_give_the_same_thing(): void
    {
        // Le test qui dit pourquoi ce réglage ne peut pas être deviné.
        $comprise = Tax::split(100.0, 10.0, true);
        $horsTaxe = Tax::split(100.0, 10.0, false);

        $this->assertSame(100.0, $comprise['total']);
        $this->assertSame(110.0, $horsTaxe['total']);
    }

    /* ------------------------------------------------------------------
       Le centime : un reçu qui ne tombe pas juste est refusé.
       ------------------------------------------------------------------ */

    public function test_base_plus_tax_always_equals_the_total(): void
    {
        // 100 / 1,1 = 90,909… Arrondir la base et la taxe chacune de leur côté
        // laisserait un écart d'un centime sur certains montants.
        for ($montant = 1; $montant <= 400; $montant++) {
            foreach ([10.0, 18.0, 15.0, 20.0, 5.5] as $taux) {
                foreach ([true, false] as $comprise) {
                    $r = Tax::split((float) $montant, $taux, $comprise);

                    $this->assertSame(
                        $r['total'],
                        round($r['base'] + $r['tax'], 2),
                        "base + taxe ≠ total pour $montant à $taux % (comprise : ".var_export($comprise, true).')'
                    );
                }
            }
        }
    }

    public function test_an_awkward_amount_still_adds_up(): void
    {
        $r = Tax::split(33.33, 18.0, true);

        $this->assertSame(33.33, round($r['base'] + $r['tax'], 2));
    }

    /* ------------------------------------------------------------------
       Ce qui n'est pas taxé.
       ------------------------------------------------------------------ */

    public function test_an_exempt_article_carries_no_tax(): void
    {
        $r = Tax::split(100.0, Tax::EXEMPT, true);

        $this->assertSame(100.0, $r['base']);
        $this->assertSame(0.0, $r['tax']);
        $this->assertSame(100.0, $r['total']);
    }

    public function test_an_absurd_rate_is_refused_not_applied(): void
    {
        // Un taux négatif rendrait de l'argent ; 100 % rendrait la base nulle.
        $this->assertFalse(Tax::isValidRate(-5));
        $this->assertFalse(Tax::isValidRate(100));
        $this->assertFalse(Tax::isValidRate('beaucoup'));
        $this->assertNull(Tax::rate(-5));

        // Refusé veut dire ignoré, pas appliqué de travers.
        $this->assertSame(0.0, Tax::split(100.0, -5, true)['tax']);
    }

    public function test_a_missing_rate_is_not_zero_percent_by_accident(): void
    {
        // Null = « non renseigné ». Le service décidera de retomber sur le taux
        // du commerce ; ici on ne l'invente pas.
        $this->assertNull(Tax::rate(null));
        $this->assertSame(10.0, Tax::rate(10));
    }

    /* ------------------------------------------------------------------
       Le panier : plusieurs taux, et la remise.
       ------------------------------------------------------------------ */

    public function test_a_basket_is_broken_down_by_rate(): void
    {
        // Dès qu'un commerce vend de l'exonéré à côté du taxé, c'est ce que la
        // déclaration demande — un total unique ne permet plus de le refaire.
        $r = Tax::summarize([
            ['amount' => 110.0, 'rate' => 10.0],
            ['amount' => 50.0,  'rate' => 0.0],
        ], true);

        $this->assertSame(160.0, $r['total']);
        $this->assertSame(10.0, $r['tax']);
        $this->assertSame(150.0, $r['base']);

        $this->assertSame(10.0, $r['byRate']['10']['tax']);
        $this->assertSame(0.0, $r['byRate']['0']['tax']);
    }

    public function test_the_same_rate_written_twice_is_counted_once(): void
    {
        // « 10 » et « 10.0 » doivent se ranger ensemble, sinon la déclaration
        // compte deux fois le même taux.
        $r = Tax::summarize([
            ['amount' => 110.0, 'rate' => 10],
            ['amount' => 110.0, 'rate' => 10.0],
        ], true);

        $this->assertCount(1, $r['byRate']);
        $this->assertSame(20.0, $r['byRate']['10']['tax']);
    }

    public function test_a_discount_lowers_the_taxable_base_proportionally(): void
    {
        // Imputer la remise entièrement sur une ligne changerait la taxe due
        // selon l'ordre des articles — et ferait payer au commerce une taxe
        // qu'il n'a pas encaissée.
        $r = Tax::summarize([
            ['amount' => 110.0, 'rate' => 10.0],
            ['amount' => 110.0, 'rate' => 10.0],
        ], true, 110.0);

        $this->assertSame(110.0, $r['total'], 'La moitié du panier a été remisée.');
        $this->assertSame(10.0, $r['tax'], 'La taxe suit, elle ne reste pas sur le montant plein.');
    }

    public function test_a_discount_spreads_across_different_rates(): void
    {
        $r = Tax::summarize([
            ['amount' => 100.0, 'rate' => 10.0],
            ['amount' => 100.0, 'rate' => 0.0],
        ], true, 100.0);

        // Chaque ligne perd la moitié : 50 taxés, 50 exonérés.
        $this->assertSame(100.0, $r['total']);
        $this->assertSame(50.0, $r['byRate']['10']['base'] + $r['byRate']['10']['tax']);
        $this->assertSame(50.0, $r['byRate']['0']['base']);
    }

    public function test_a_discount_larger_than_the_basket_empties_it(): void
    {
        // Un article offert : rien n'est dû, et surtout pas une taxe négative.
        $r = Tax::summarize([['amount' => 100.0, 'rate' => 10.0]], true, 500.0);

        $this->assertSame(0.0, $r['total']);
        $this->assertSame(0.0, $r['tax']);
    }

    public function test_an_empty_basket_says_nothing_and_does_not_divide_by_zero(): void
    {
        $r = Tax::summarize([], true, 50.0);

        $this->assertSame(0.0, $r['total']);
        $this->assertSame([], $r['byRate']);
    }

    public function test_the_highest_rate_is_listed_first(): void
    {
        $r = Tax::summarize([
            ['amount' => 100.0, 'rate' => 0.0],
            ['amount' => 118.0, 'rate' => 18.0],
            ['amount' => 110.0, 'rate' => 10.0],
        ], true);

        // PHP retransforme les clés numériques en entiers : on compare donc
        // les valeurs, pas leur type.
        $this->assertSame([18, 10, 0], array_keys($r['byRate']));
        $this->assertSame(18.0, $r['byRate'][18]['rate']);
    }

    /* ------------------------------------------------------------------
       Ce qui s'imprime.
       ------------------------------------------------------------------ */

    public function test_the_receipt_label_says_which_tax_and_how_much(): void
    {
        $this->assertSame('TCA 10 %', Tax::label('TCA', 10.0));
        $this->assertSame('ITBIS 18 %', Tax::label('ITBIS', 18));
        $this->assertSame('TVA 5.5 %', Tax::label('TVA', 5.5));
        $this->assertSame('Taxe', Tax::label(null, null));
    }

    public function test_every_suggested_tax_is_usable(): void
    {
        foreach (Tax::SUGGESTED as $pays => $meta) {
            $this->assertNotEmpty($meta['label'], "La taxe de $pays n'a pas de nom.");
            $this->assertTrue(Tax::isValidRate($meta['rate']), "Le taux de $pays est refusé.");
            $this->assertNotEmpty($meta['country']);
        }
    }
}
