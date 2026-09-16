<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Loyalty\CustomerSegment;
use PHPUnit\Framework\TestCase;

/**
 * Dans quel groupe ranger un porteur de carte — logique pure, sans base.
 *
 * La règle qui gouverne tout ce fichier : INACTIF passe avant VIP. Un client
 * fidèle qui a disparu est l'alerte la plus importante ; une étiquette
 * flatteuse ne doit jamais la cacher.
 */
class CustomerSegmentTest extends TestCase
{
    public function test_a_freshly_issued_card_with_no_history_is_new(): void
    {
        $this->assertSame(CustomerSegment::NOUVEAU, CustomerSegment::classify([
            'points' => 0, 'issued_days_ago' => 1, 'last_transaction_days_ago' => null,
        ]));
    }

    public function test_a_new_card_stays_new_even_with_a_big_first_purchase(): void
    {
        // Un seul geste ne fait pas un VIP : le marchand veut savoir qui vient
        // d'arriver, pas le confondre avec un habitué de longue date.
        $this->assertSame(CustomerSegment::NOUVEAU, CustomerSegment::classify([
            'points' => 600, 'issued_days_ago' => 2, 'last_transaction_days_ago' => 1,
        ]));
    }

    public function test_a_card_never_used_becomes_inactive_once_it_had_time_to_serve(): void
    {
        // Émise il y a un an, jamais utilisée : elle a eu tout le temps de
        // servir, et n'a pas servi. Ce n'est plus « nouveau », c'est un échec.
        $this->assertSame(CustomerSegment::INACTIF, CustomerSegment::classify([
            'points' => 0, 'issued_days_ago' => 365, 'last_transaction_days_ago' => null,
        ]));
    }

    public function test_a_high_spender_who_stopped_coming_is_flagged_inactive_not_vip(): void
    {
        // LA règle du fichier. Le client qui achetait beaucoup et qui a
        // disparu est le plus important à rappeler — l'alerte doit gagner sur
        // le compliment.
        $this->assertSame(CustomerSegment::INACTIF, CustomerSegment::classify([
            'points' => 5000, 'issued_days_ago' => 400, 'last_transaction_days_ago' => 120,
        ]));
    }

    public function test_a_loyal_high_points_customer_still_active_is_vip(): void
    {
        $this->assertSame(CustomerSegment::VIP, CustomerSegment::classify([
            'points' => 500, 'issued_days_ago' => 200, 'last_transaction_days_ago' => 5,
        ]));
    }

    public function test_a_regular_customer_with_recent_activity_is_active(): void
    {
        $this->assertSame(CustomerSegment::ACTIF, CustomerSegment::classify([
            'points' => 80, 'issued_days_ago' => 200, 'last_transaction_days_ago' => 10,
        ]));
    }

    public function test_the_exact_thresholds_are_inclusive_where_documented(): void
    {
        // Pile au seuil VIP : compte comme VIP, pas juste en-dessous.
        $this->assertSame(CustomerSegment::VIP, CustomerSegment::classify([
            'points' => CustomerSegment::SEUIL_VIP_POINTS, 'issued_days_ago' => 100, 'last_transaction_days_ago' => 1,
        ]));
        // Un point de moins : pas VIP.
        $this->assertSame(CustomerSegment::ACTIF, CustomerSegment::classify([
            'points' => CustomerSegment::SEUIL_VIP_POINTS - 1, 'issued_days_ago' => 100, 'last_transaction_days_ago' => 1,
        ]));
        // Pile à la limite d'inactivité : encore actif.
        $this->assertSame(CustomerSegment::ACTIF, CustomerSegment::classify([
            'points' => 10, 'issued_days_ago' => 100, 'last_transaction_days_ago' => CustomerSegment::JOURS_INACTIF,
        ]));
        // Un jour de plus : inactif.
        $this->assertSame(CustomerSegment::INACTIF, CustomerSegment::classify([
            'points' => 10, 'issued_days_ago' => 100, 'last_transaction_days_ago' => CustomerSegment::JOURS_INACTIF + 1,
        ]));
    }

    public function test_missing_facts_never_crash_the_classifier(): void
    {
        // Une carte mal chargée (requête incomplète) ne doit jamais faire
        // planter l'écran du marchand.
        $this->assertSame(CustomerSegment::NOUVEAU, CustomerSegment::classify([]));
    }

    public function test_every_segment_has_a_human_label(): void
    {
        foreach ([CustomerSegment::NOUVEAU, CustomerSegment::INACTIF, CustomerSegment::VIP, CustomerSegment::ACTIF] as $s) {
            $this->assertNotSame($s, CustomerSegment::label($s));
        }
    }
}
