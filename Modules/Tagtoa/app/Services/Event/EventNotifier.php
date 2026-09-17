<?php

namespace Modules\Tagtoa\App\Services\Event;

use Modules\Tagtoa\App\Models\Event\Delivery;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;

/**
 * TAGTOA Event — livraison des confirmations CLIENT (achat, entrée), et le
 * journal qui en garde la trace.
 *
 * Diffère volontairement de `NotificationService::push()` (file d'attente,
 * générique, partagée avec MENU et STORE) : ici l'envoi est SYNCHRONE, pour
 * connaître le résultat réel de chaque canal — c'est justement ce que `push()`
 * ne peut pas dire, puisqu'il se contente de déposer un job. « Statistiques
 * de livraison » n'a de sens que si quelque chose sait vraiment ce qui s'est
 * passé, canal par canal.
 *
 * Tolérant de bout en bout : un échec d'envoi ou de journalisation ne doit
 * jamais remonter jusqu'à l'achat ou au check-in eux-mêmes.
 */
class EventNotifier
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function notifyCustomer(
        Event $event,
        string $context,
        string $subject,
        string $body,
        ?string $email,
        ?string $phone,
        ?int $ticketId = null
    ): void {
        if ($email) {
            $this->attempt($event, $context, 'email', $email, $ticketId, fn () => $this->notifications->email($email, $subject, $body));
        }

        if ($phone) {
            $this->attempt($event, $context, 'whatsapp', $phone, $ticketId, fn () => $this->notifications->whatsapp($phone, $subject."\n".$body));
        }
    }

    private function attempt(Event $event, string $context, string $channel, string $recipient, ?int $ticketId, callable $send): void
    {
        $status = Delivery::STATUS_NOT_SENT;
        try {
            $status = $send() ? Delivery::STATUS_SENT : Delivery::STATUS_NOT_SENT;
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        try {
            Delivery::create([
                'event_id'  => $event->id,
                'ticket_id' => $ticketId,
                'context'   => $context,
                'channel'   => $channel,
                'recipient' => $recipient,
                'status'    => $status,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
