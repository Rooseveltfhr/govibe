<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Loyalty\Transaction;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\Tests\TestCase;

/**
 * NotificationService::loyaltyMovementMessage() — PUR (aucun Eloquent, aucun
 * I/O) : reçoit des faits déjà calculés, rend un sujet + corps. Étend le
 * TestCase Feature (pas le PHPUnit\Framework\TestCase nu de LuhnTest)
 * uniquement parce que __() exige le traducteur Laravel — la fonction
 * elle-même ne touche ni la base ni le réseau.
 *
 * RefreshDatabase, bien qu'aucun test ici ne touche la base : sans lui,
 * Testbench remigre/rétrograde entre chaque méthode d'un même fichier, et une
 * migration existante (2026_09_22_000142) échoue à SA rétrogradation sous
 * SQLite (suppression d'une colonne indexée) — un défaut pré-existant, sans
 * rapport avec Loyalty, qu'aucun autre test Feature du module ne déclenche
 * car ils utilisent tous ce trait.
 */
class LoyaltyMovementMessageTest extends TestCase
{
    use RefreshDatabase;

    private function faits(array $overrides = []): array
    {
        return array_merge([
            'type' => Transaction::TYPE_TOP_UP,
            'reward' => false,
            'cardholder_name' => 'Jean',
            'program_name' => 'Fidélité',
            'currency' => 'HTG',
            'amount' => 100.0,
            'points_delta' => 100,
            'balance' => 600.0,
            'points' => 600,
            'reward_note' => null,
        ], $overrides);
    }

    public function test_une_recharge_annonce_le_montant_et_le_nouveau_solde(): void
    {
        $message = NotificationService::loyaltyMovementMessage($this->faits());

        $this->assertStringContainsString('Carte rechargée', $message['subject']);
        $this->assertStringContainsString('100', $message['body']);
        $this->assertStringContainsString('600', $message['body']);
    }

    public function test_des_points_gagnes_sans_achat_ne_mentionnent_pas_de_montant(): void
    {
        $message = NotificationService::loyaltyMovementMessage($this->faits([
            'type' => Transaction::TYPE_EARN,
            'points_delta' => 30,
        ]));

        $this->assertStringContainsString('Points gagnés', $message['subject']);
        $this->assertStringContainsString('30', $message['body']);
    }

    public function test_une_recompense_echangee_reprend_la_note_du_ledger(): void
    {
        $message = NotificationService::loyaltyMovementMessage($this->faits([
            'type' => Transaction::TYPE_REDEEM,
            'reward' => true,
            'reward_note' => 'Récompense : Café offert',
        ]));

        $this->assertStringContainsString('Récompense échangée', $message['subject']);
        $this->assertStringContainsString('Café offert', $message['body']);
    }

    public function test_un_debit_sans_recompense_est_un_paiement_pas_une_recompense(): void
    {
        $message = NotificationService::loyaltyMovementMessage($this->faits([
            'type' => Transaction::TYPE_REDEEM,
            'reward' => false,
            'amount' => 120.0,
        ]));

        $this->assertStringContainsString('Paiement effectué', $message['subject']);
        $this->assertStringNotContainsString('Récompense', $message['subject']);
    }

    public function test_un_type_inconnu_ne_rend_rien(): void
    {
        $message = NotificationService::loyaltyMovementMessage($this->faits(['type' => 'autre_chose']));

        $this->assertNull($message);
    }
}
