<?php

namespace App\Notifications;

use App\Models\TaGtoaPaymentProof;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TAGTOA PAY — le propriétaire reçoit une notification quand un client
 * soumet une preuve de paiement. Canaux : database + mail.
 */
class TaGtoaPayProofReceived extends Notification
{
    use Queueable;

    public function __construct(public TaGtoaPaymentProof $proof)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $proof    = $this->proof;
        $pageName = optional($proof->page)->title ?: ('Page #' . $proof->payment_page_id);
        $amount   = $proof->amount
            ? number_format((float) $proof->amount, 2) . ' ' . $proof->currency
            : __('Montant non précisé');

        return (new MailMessage())
            ->subject(__('TAGTOA PAY — Nouvelle preuve de paiement'))
            ->greeting(__('Bonjou!'))
            ->line(__(':payer vient de soumettre une preuve de paiement.', ['payer' => $proof->payer_name]))
            ->line(__('Page : :page', ['page' => $pageName]))
            ->line(__('Méthode : :method', ['method' => optional($proof->method)->display_label]))
            ->line(__('Montant : :amount', ['amount' => $amount]))
            ->action(__('Vérifier la preuve'), url('/tagtoa/pay/' . $proof->payment_page_id . '/proofs'))
            ->line(__('Mèsi paske w ap itilize TAGTOA.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'tagtoa_pay_proof',
            'proof_id'          => $this->proof->id,
            'payment_page_id'   => $this->proof->payment_page_id,
            'payment_method_id' => $this->proof->payment_method_id,
            'payer_name'        => $this->proof->payer_name,
            'amount'            => $this->proof->amount,
            'currency'          => $this->proof->currency,
            'message'           => __(':payer a soumis une preuve de paiement.', ['payer' => $this->proof->payer_name]),
        ];
    }
}
