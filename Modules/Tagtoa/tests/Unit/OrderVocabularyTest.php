<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * Les quatre modules doivent tenir dans le vocabulaire commun.
 *
 * C'est la garde de la colonne vertébrale. Un statut de module absent du
 * vocabulaire commun ne provoque aucune erreur : il est simplement ignoré, et
 * le rapport affiche alors une commande déjà livrée comme encore à préparer.
 * Une divergence silencieuse est pire qu'une panne — on ne la découvre qu'en
 * cherchant pourquoi les chiffres ne tombent pas.
 */
class OrderVocabularyTest extends TestCase
{
    /** Les statuts que chaque module écrit chez lui. */
    private const MODULES = [
        'menu'  => ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'],
        'store' => ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'],
    ];

    public function test_every_module_status_exists_in_the_common_vocabulary(): void
    {
        foreach (self::MODULES as $module => $statuts) {
            foreach ($statuts as $s) {
                $this->assertTrue(
                    OrderStatus::isValid($s),
                    "Le statut « $s » du module $module n'existe pas dans le vocabulaire commun : ".
                    'la colonne vertébrale l\'ignorerait en silence.'
                );
            }
        }
    }

    public function test_the_ticketing_integers_all_translate(): void
    {
        // La billetterie compte en entiers parce qu'elle est antérieure à cette
        // table. La traduire évite de toucher un module qui fonctionne.
        foreach ([0, 1, 2] as $entier) {
            $this->assertTrue(OrderStatus::isValid(OrderStatus::fromEvent($entier)));
        }

        $this->assertSame(OrderStatus::COMPLETED, OrderStatus::fromEvent(1));
        $this->assertSame(OrderStatus::CANCELLED, OrderStatus::fromEvent(2));
    }

    public function test_an_unknown_integer_does_not_invent_a_paid_order(): void
    {
        // Se tromper vers « payée » ferait entrer dans la recette de l'argent
        // que le commerce n'a pas reçu. Le repli va vers l'attente.
        $this->assertSame(OrderStatus::PENDING, OrderStatus::fromEvent(99));
    }

    public function test_every_status_has_a_label(): void
    {
        foreach (OrderStatus::ALL as $s) {
            $this->assertNotSame('Inconnu', OrderStatus::label($s), "Le statut « $s » n'a pas de libellé.");
        }
        foreach (OrderStatus::PAYMENTS as $p) {
            $this->assertNotSame('Inconnu', OrderStatus::paymentLabel($p), "Le paiement « $p » n'a pas de libellé.");
        }
    }

    public function test_every_channel_has_a_label(): void
    {
        foreach (Channel::ALL as $c) {
            $this->assertNotSame('Autre', Channel::label($c), "Le canal « $c » n'a pas de libellé.");
        }
    }

    /* ------------------------------------------------------------------
       Ce qui compte dans la recette.
       ------------------------------------------------------------------ */

    public function test_a_cancelled_order_never_counts_as_revenue(): void
    {
        // La compter gonflerait la recette d'argent que le commerce n'a pas.
        $this->assertFalse(OrderStatus::countsAsRevenue(OrderStatus::CANCELLED, OrderStatus::PAID));
        $this->assertFalse(OrderStatus::countsAsRevenue(OrderStatus::REFUNDED, OrderStatus::PAID));
    }

    public function test_an_unpaid_order_never_counts_as_revenue(): void
    {
        // Une commande livrée mais jamais réglée — le voisin qui passe payer
        // demain — n'est pas de la recette tant qu'il n'a pas payé.
        $this->assertFalse(OrderStatus::countsAsRevenue(OrderStatus::COMPLETED, OrderStatus::UNPAID));
        $this->assertTrue(OrderStatus::countsAsRevenue(OrderStatus::COMPLETED, OrderStatus::PAID));
    }

    public function test_a_deposit_counts_for_what_was_received(): void
    {
        $this->assertTrue(OrderStatus::countsAsRevenue(OrderStatus::PREPARING, OrderStatus::PARTIAL));
    }

    public function test_a_closed_order_is_no_longer_open(): void
    {
        $this->assertTrue(OrderStatus::isOpen(OrderStatus::PREPARING));
        $this->assertFalse(OrderStatus::isOpen(OrderStatus::COMPLETED));
        $this->assertFalse(OrderStatus::isOpen(OrderStatus::CANCELLED));
    }

    /* ------------------------------------------------------------------
       L'état du paiement, déduit des montants.
       ------------------------------------------------------------------ */

    public function test_a_split_that_leaves_a_cent_is_still_paid(): void
    {
        // Un split en trois laisse souvent un arrondi. Refuser de marquer
        // « payée » pour 0,01 obligerait le caissier à bricoler un ajustement
        // à chaque fois.
        $this->assertSame(OrderStatus::PAID, OrderStatus::paymentFor(99.99, 100.0));
        $this->assertSame(OrderStatus::PARTIAL, OrderStatus::paymentFor(99.0, 100.0));
    }

    public function test_nothing_paid_is_unpaid(): void
    {
        $this->assertSame(OrderStatus::UNPAID, OrderStatus::paymentFor(0.0, 100.0));
    }

    public function test_a_free_order_does_not_wait_for_a_payment(): void
    {
        // Un article offert, une invitation : rien à encaisser, rien à
        // réclamer non plus.
        $this->assertSame(OrderStatus::UNPAID, OrderStatus::paymentFor(0.0, 0.0));
        $this->assertSame(OrderStatus::PAID, OrderStatus::paymentFor(5.0, 0.0));
    }

    public function test_only_the_counter_collects_on_the_spot(): void
    {
        // Une vente de caisse n'a pas à attendre de confirmation, et une
        // commande QR n'est pas payée du seul fait d'exister.
        $this->assertTrue(Channel::isImmediate(Channel::POS));
        $this->assertFalse(Channel::isImmediate(Channel::MENU));
        $this->assertFalse(Channel::isImmediate(Channel::STORE));
    }
}
