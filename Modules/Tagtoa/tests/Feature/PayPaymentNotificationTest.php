<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — notifier quand l'argent arrive RÉELLEMENT
|--------------------------------------------------------------------------
| Deux chemins créent une preuve APPROUVÉE sans revue manuelle : la Carte
| TAGTOA (CardWalletService::charge, synchrone) et une passerelle en ligne
| (CheckoutService::applyPayPagePaid, sur confirmation). Avant ce correctif,
| ni le marchand ni le payeur n'apprenaient jamais que le paiement avait
| abouti — seule la soumission MANUELLE d'une preuve (en attente de revue)
| déclenchait une notification (PayProofReceived).
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Card\CardWalletService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\Tests\TestCase;

class PayPaymentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function espionner(): \Mockery\MockInterface
    {
        $spy = \Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $spy);

        return $spy;
    }

    private function page(string $tenant = 't-1'): PaymentPage
    {
        return PaymentPage::create([
            'tenant_id' => $tenant, 'alias' => 'boutique-'.random_int(1000, 9999), 'title' => 'Boutique',
            'default_currency' => 'HTG', 'is_active' => true,
        ]);
    }

    /** Un moyen ACTIF du marchand — sans lui, ni la Carte TAGTOA ni une
     *  passerelle ne peuvent légitimement débiter qui que ce soit (voir la
     *  garde ajoutée dans PublicController::cardCharge()). */
    private function methode(string $type, string $tenant = 't-1'): PaymentMethod
    {
        return PaymentMethod::create([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id,
            'tenant_id'       => $tenant,
            'type'            => $type,
            'is_active'       => true,
        ]);
    }

    /* ------------------------------------------------------------------
       Le contenu du message — PUR, sans Eloquent ni réseau.
       ------------------------------------------------------------------ */

    public function test_le_message_marchand_donne_le_montant_et_le_payeur(): void
    {
        $messages = NotificationService::paymentReceivedMessages([
            'page_title' => 'Boutique', 'payer_name' => 'Jean', 'amount' => 500.0,
            'currency' => 'HTG', 'method_label' => 'MonCash',
        ]);

        $this->assertStringContainsString('Paiement reçu', $messages['merchant']['subject']);
        $this->assertStringContainsString('500', $messages['merchant']['body']);
        $this->assertStringContainsString('Jean', $messages['merchant']['body']);
        $this->assertStringContainsString('MonCash', $messages['merchant']['body']);
    }

    public function test_le_recu_payeur_confirme_le_montant(): void
    {
        $messages = NotificationService::paymentReceivedMessages([
            'page_title' => 'Boutique', 'payer_name' => 'Jean', 'amount' => 500.0,
            'currency' => 'HTG', 'method_label' => null,
        ]);

        $this->assertStringContainsString('Jean', $messages['payer']['body']);
        $this->assertStringContainsString('500', $messages['payer']['body']);
    }

    public function test_un_payeur_sans_nom_reste_poli(): void
    {
        $messages = NotificationService::paymentReceivedMessages([
            'page_title' => 'Boutique', 'payer_name' => '', 'amount' => 100.0,
            'currency' => 'HTG', 'method_label' => null,
        ]);

        $this->assertStringContainsString('Client', $messages['payer']['body']);
    }

    /* ------------------------------------------------------------------
       Le câblage — via un double, jamais le réseau (voir LOYALTY/EVENT).
       ------------------------------------------------------------------ */

    public function test_une_recharge_par_carte_notifie_le_marchand_et_le_payeur(): void
    {
        $page = $this->page();
        $page->setRelation('vcard', (object) ['email' => 'proprietaire@ex.com']);
        $this->methode('tagtoa_card');

        $card = app(CardWalletService::class)->issue('UID-PAY-1', [
            'tenant_id' => 't-1', 'holder_name' => 'Jean', 'holder_phone' => '38112345', 'currency' => 'HTG',
        ]);
        app(CardWalletService::class)->topUp($card, 1000, ['tenant_id' => 't-1']);

        $spy = $this->espionner();
        $spy->shouldReceive('notifyPaymentReceived')->once()
            ->withArgs(fn ($p, $proof) => $p->id === $page->id && (float) $proof->amount === 200.0);

        $this->post(route('tagtoa.pay.card.charge', $page->alias), [
            'card_uid' => 'UID-PAY-1', 'amount' => 200,
        ])->assertRedirect();
    }

    public function test_un_paiement_en_ligne_confirme_notifie_une_seule_fois_meme_rejoue(): void
    {
        $page = $this->page();
        $page->setRelation('vcard', (object) ['email' => 'proprietaire@ex.com']);
        $method = $this->methode('moncash');

        $txn = PayTransaction::create([
            'tenant_id' => 't-1', 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'pay_page', 'order_id' => $page->id, 'amount' => 300, 'currency' => 'HTG',
            'status' => PayTransaction::STATUS_PENDING,
            'meta' => ['method_id' => $method->id, 'payer_name' => 'Marie', 'payer_phone' => '38199999'],
        ]);

        $spy = $this->espionner();
        $spy->shouldReceive('notifyPaymentReceived')->once()
            ->withArgs(fn ($p, $proof) => $p->id === $page->id && $proof->reference === $txn->reference);

        $svc = app(CheckoutService::class);
        $appliquer = new \ReflectionMethod($svc, 'applyPayPagePaid');
        $appliquer->setAccessible(true);

        // Appliqué deux fois — un webhook rejoué de la passerelle — la même
        // preuve est retrouvée (firstOrCreate), donc UNE seule notification.
        $appliquer->invoke($svc, $txn);
        $appliquer->invoke($svc, $txn);
    }
}
