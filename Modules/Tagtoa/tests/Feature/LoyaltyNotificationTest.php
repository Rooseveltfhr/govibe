<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Loyalty\Transaction;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\Tests\TestCase;

/**
 * LE CÂBLAGE des notifications de fidélité : LoyaltyCardService doit appeler
 * NotificationService::notifyLoyaltyMovement() APRÈS chaque mouvement
 * enregistré — jamais avant, jamais si le mouvement n'a pas eu lieu.
 *
 * Le CONTENU du message est couvert à part, pur, par LoyaltyMovementMessageTest.
 * L'ENVOI RÉEL (e-mail/WhatsApp) dépend de guzzlehttp/guzzle, absent du vendor
 * de ce module dans cet environnement de test (composer install partiel) — un
 * test qui prétendrait vérifier une requête HTTP sortante mentirait sur ce
 * qu'il couvre. On isole donc ici la seule chose que LoyaltyCardService doit
 * garantir : QUE l'appel a lieu, avec la bonne carte et la bonne transaction —
 * via un double de NotificationService, jamais le réseau.
 */
class LoyaltyNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function programme(): Program
    {
        return Program::create([
            'name' => 'Fidélité', 'alias' => 'fidelite-notif-'.random_int(1000, 9999),
            'points_per_dollar' => 1, 'currency' => 'HTG',
        ]);
    }

    private function espionner(): \Mockery\MockInterface
    {
        $spy = \Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $spy);

        return $spy;
    }

    public function test_une_recharge_declenche_la_notification_du_mouvement(): void
    {
        $spy = $this->espionner();
        $program = $this->programme();
        $card = app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Jean'])['card'];

        $spy->shouldReceive('notifyLoyaltyMovement')->once()
            ->withArgs(fn ($c, $tx) => $c->id === $card->id
                && $tx->type === Transaction::TYPE_TOP_UP
                && (float) $tx->amount === 500.0);

        app(LoyaltyCardService::class)->topUp($card, 500);
    }

    public function test_des_points_gagnes_declenchent_la_notification(): void
    {
        $spy = $this->espionner();
        $program = $this->programme();
        $card = app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Jean'])['card'];

        $spy->shouldReceive('notifyLoyaltyMovement')->once()
            ->withArgs(fn ($c, $tx) => $tx->type === Transaction::TYPE_EARN && $tx->points_delta === 30);

        app(LoyaltyCardService::class)->earnPoints($card, 30);
    }

    public function test_zero_point_gagne_ne_declenche_rien(): void
    {
        $spy = $this->espionner();
        $program = $this->programme();
        $card = app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Jean'])['card'];

        $spy->shouldNotReceive('notifyLoyaltyMovement');

        app(LoyaltyCardService::class)->earnPoints($card, 0);
    }

    public function test_un_debit_declenche_la_notification(): void
    {
        $spy = $this->espionner();
        $program = $this->programme();
        $card = app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Jean', 'balance' => 500])['card'];

        $spy->shouldReceive('notifyLoyaltyMovement')->once()
            ->withArgs(fn ($c, $tx) => $tx->type === Transaction::TYPE_REDEEM && $tx->reward_id === null);

        app(LoyaltyCardService::class)->redeem($card, 120);
    }

    public function test_une_recompense_echangee_declenche_la_notification_avec_le_reward_id(): void
    {
        $spy = $this->espionner();
        $program = $this->programme();
        $reward = $program->rewards()->create(['name' => 'Café offert', 'points_required' => 50, 'is_active' => true]);
        $card = app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Jean'])['card'];
        $card->forceFill(['points' => 100])->save();

        $spy->shouldReceive('notifyLoyaltyMovement')->once()
            ->withArgs(fn ($c, $tx) => $tx->reward_id === $reward->id);

        app(LoyaltyCardService::class)->redeemReward($card->refresh(), $reward);
    }

    /**
     * GARDE : un débit refusé (solde insuffisant) ne doit JAMAIS notifier —
     * rien n'a été écrit dans le ledger, il n'y a rien à annoncer.
     */
    public function test_un_debit_refuse_ne_notifie_pas(): void
    {
        $spy = $this->espionner();
        $program = $this->programme();
        $card = app(LoyaltyCardService::class)->issueCard($program, ['cardholder_name' => 'Jean', 'balance' => 10])['card'];

        $spy->shouldNotReceive('notifyLoyaltyMovement');

        try {
            app(LoyaltyCardService::class)->redeem($card, 9999);
        } catch (\RuntimeException $e) {
            // attendu : solde insuffisant
        }
    }
}
