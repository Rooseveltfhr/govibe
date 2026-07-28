<?php

namespace Modules\Tagtoa\App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;

/**
 * TAGTOA Pay — notification au propriétaire quand une preuve est soumise.
 */
class PayProofReceived extends Notification
{
    use Queueable;

    public function __construct(public PaymentProof $proof)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->proof;
        $amount = $p->amount ? number_format((float) $p->amount, 2).' '.$p->currency : __('Montant non précisé');

        return (new MailMessage())
            ->subject(__('TAGTOA — Nouvelle preuve de paiement'))
            ->greeting(__('Bonjou!'))
            ->line(__(':payer a soumis une preuve de paiement.', ['payer' => $p->payer_name]))
            ->line(__('Méthode : :m', ['m' => optional($p->method)->display_label]))
            ->line(__('Montant : :a', ['a' => $amount]))
            ->action(__('Vérifier'), url('/tagtoa/pay/'.$p->payment_page_id.'/proofs'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'tagtoa_pay_proof',
            'proof_id' => $this->proof->id,
            'page_id'  => $this->proof->payment_page_id,
            'payer'    => $this->proof->payer_name,
            'amount'   => $this->proof->amount,
            'message'  => __(':payer a soumis une preuve de paiement.', ['payer' => $this->proof->payer_name]),
        ];
    }
}
