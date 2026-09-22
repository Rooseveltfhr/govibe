<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| NotificationService::orderStatusMessage() — PUR (aucun Eloquent, aucun
| I/O) : reçoit des faits déjà calculés, rend un sujet + corps, ou null pour
| un statut qui ne dit rien de neuf au client (en attente, en préparation,
| annulée — cette dernière volontairement hors périmètre de ce correctif).
|
| RefreshDatabase pour la même raison que LoyaltyMovementMessageTest : __()
| exige le traducteur Laravel, et sans ce trait Testbench rétrograde une
| migration pré-existante qui échoue sous SQLite (sans rapport avec ce test).
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\Tests\TestCase;

class OrderStatusMessageTest extends TestCase
{
    use RefreshDatabase;

    private function faits(array $overrides = []): array
    {
        return array_merge([
            'status' => 'confirmed', 'reference' => 'MN-000042', 'menu_name' => 'Lounge 509',
        ], $overrides);
    }

    public function test_a_confirmed_order_gets_a_confirmation_message(): void
    {
        $message = NotificationService::orderStatusMessage($this->faits(['status' => 'confirmed']));

        $this->assertNotNull($message);
        $this->assertStringContainsString('confirmée', $message['body']);
        $this->assertStringContainsString('MN-000042', $message['body']);
    }

    public function test_a_ready_order_gets_an_en_route_message(): void
    {
        $message = NotificationService::orderStatusMessage($this->faits(['status' => 'ready']));

        $this->assertNotNull($message);
        $this->assertStringContainsString('en route', $message['body']);
    }

    public function test_a_completed_order_gets_a_delivered_message(): void
    {
        $message = NotificationService::orderStatusMessage($this->faits(['status' => 'completed']));

        $this->assertNotNull($message);
        $this->assertStringContainsString('livrée', $message['body']);
    }

    public function test_statuses_that_tell_the_customer_nothing_new_get_no_message(): void
    {
        foreach (['pending', 'preparing', 'cancelled'] as $statut) {
            $this->assertNull(
                NotificationService::orderStatusMessage($this->faits(['status' => $statut])),
                "Le statut « $statut » ne devrait produire aucun message."
            );
        }
    }

    public function test_the_subject_names_the_menu_and_the_reference(): void
    {
        $message = NotificationService::orderStatusMessage($this->faits(['menu_name' => 'Bar Puya']));

        $this->assertStringContainsString('MN-000042', $message['subject']);
        $this->assertStringContainsString('Bar Puya', $message['subject']);
    }
}
