<?php

namespace Modules\Tagtoa\App\Services\Event;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\NfcTag;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;

/**
 * TAGTOA Event — moteur de check-in (scanner PWA, offline-first).
 *
 * processScan() -> ['valid','color','sound','message','ticket'].
 */
class CheckinService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    /** Résout un UID de carte NFC -> code du billet (pour check-in par tap). */
    public function resolveNfcCode(Event $event, string $uid): ?string
    {
        $tag = NfcTag::where('event_id', $event->id)
            ->where('uid_hash', NfcTag::hashUid($uid))
            ->where('status', 'active')->first();

        return ($tag && $tag->ticket_id)
            ? optional(Ticket::find($tag->ticket_id))->code
            : null;
    }

    public function processScan(Event $event, string $code, string $direction = 'in', string $method = 'qr', ?string $gate = null, ?string $clientUuid = null, ?int $staffId = null): array
    {
        $ticket = Ticket::where('event_id', $event->id)->where('code', $code)->with('ticketType')->first();

        if (! $ticket) {
            return $this->r(false, 'red', 'error', __('Billet introuvable.'));
        }
        if (! $ticket->isValid()) {
            return $this->r(false, 'red', 'error', __('Billet annulé.'), $ticket);
        }
        // Anti double-entrée PAR JOUR (multi-jour) : on bloque une 2ᵉ entrée le
        // même jour, mais on autorise l'entrée un autre jour (ex. Pass 2 jours).
        // Rétro-compatible : un événement d'un seul jour ⇒ une entrée unique.
        if ($direction === 'in') {
            $today = now()->toDateString();
            $enteredToday = Checkin::where('ticket_id', $ticket->id)
                ->where('direction', 'in')
                ->whereDate('scanned_at', $today)
                ->exists();
            if ($enteredToday) {
                return $this->r(false, 'orange', 'warning', __('Déjà entré aujourd\'hui.'), $ticket);
            }
        }
        if ($direction === 'out' && ! $ticket->checked_in) {
            return $this->r(false, 'orange', 'warning', __('Pas encore entré.'), $ticket);
        }

        $entered = false;
        $result = DB::transaction(function () use ($event, $ticket, $direction, $method, $gate, $clientUuid, $staffId, &$entered) {
            if ($clientUuid && Checkin::where('ticket_id', $ticket->id)->where('client_uuid', $clientUuid)->exists()) {
                return $this->r(true, 'green', 'success', __('Déjà synchronisé.'), $ticket);
            }

            $ticket->checked_in = $direction === 'in';
            $ticket->checked_in_at = $direction === 'in' ? now() : $ticket->checked_in_at;
            $ticket->save();

            Checkin::create([
                'event_id' => $event->id, 'ticket_id' => $ticket->id, 'direction' => $direction,
                'method' => $method, 'gate' => $gate, 'staff_id' => $staffId,
                'client_uuid' => $clientUuid, 'scanned_at' => now(),
            ]);

            $entered = ($direction === 'in');

            return $this->r(true, 'green', 'success', $direction === 'in' ? __('Bienvenue!') : __('Sortie enregistrée.'), $ticket->fresh('ticketType'));
        });

        // Notifications hors transaction (tolérant) : organisateur + participant à l'entrée.
        if ($entered) {
            $this->notifyEntry($event, $ticket);
        }

        return $result;
    }

    /** Alerte organisateur (email) + confirmation participant (WhatsApp) à l'entrée. */
    protected function notifyEntry(Event $event, Ticket $ticket): void
    {
        try {
            $name = $ticket->holder_name ?: __('Participant');

            if ($event->notify_email) {
                $this->notifications->push([
                    'channels' => ['email'],
                    'email'    => $event->notify_email,
                    'subject'  => __('Entrée check-in').' — '.$event->title,
                    'body'     => __('Participant entré').' : '.$name.' — '.now()->format('H:i'),
                ]);
            }

            if ($ticket->holder_phone) {
                $this->notifications->push([
                    'channels' => ['whatsapp'],
                    'phone'    => $ticket->holder_phone,
                    'subject'  => $event->title,
                    'body'     => __('Bienvenue!').' '.__('Votre entrée est confirmée.').' — '.$name,
                ]);
            }
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    public function sync(Event $event, array $scans): array
    {
        $out = [];
        foreach ($scans as $s) {
            $out[] = [
                'client_uuid' => $s['client_uuid'] ?? null,
                'result' => $this->processScan($event, $s['code'] ?? '', $s['direction'] ?? 'in', $s['method'] ?? 'qr', $s['gate'] ?? null, $s['client_uuid'] ?? null),
            ];
        }
        return $out;
    }

    private function r(bool $valid, string $color, string $sound, string $message, ?Ticket $ticket = null): array
    {
        return [
            'valid' => $valid, 'color' => $color, 'sound' => $sound, 'message' => $message,
            'ticket' => $ticket ? [
                'code' => $ticket->code, 'holder' => $ticket->holder_name,
                'type' => optional($ticket->ticketType)->name, 'checked_in' => $ticket->checked_in,
            ] : null,
        ];
    }
}
