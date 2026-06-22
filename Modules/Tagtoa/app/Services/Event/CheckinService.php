<?php

namespace Modules\Tagtoa\App\Services\Event;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;

/**
 * TAGTOA Event — moteur de check-in (scanner PWA, offline-first).
 *
 * processScan() -> ['valid','color','sound','message','ticket'].
 */
class CheckinService
{
    public function processScan(Event $event, string $code, string $direction = 'in', string $method = 'qr', ?string $gate = null, ?string $clientUuid = null): array
    {
        $ticket = Ticket::where('event_id', $event->id)->where('code', $code)->with('ticketType')->first();

        if (! $ticket) {
            return $this->r(false, 'red', 'error', __('Billet introuvable.'));
        }
        if (! $ticket->isValid()) {
            return $this->r(false, 'red', 'error', __('Billet annulé.'), $ticket);
        }
        if ($direction === 'in' && $ticket->checked_in) {
            return $this->r(false, 'orange', 'warning', __('Déjà entré à :t', ['t' => optional($ticket->checked_in_at)->format('H:i')]), $ticket);
        }
        if ($direction === 'out' && ! $ticket->checked_in) {
            return $this->r(false, 'orange', 'warning', __('Pas encore entré.'), $ticket);
        }

        return DB::transaction(function () use ($event, $ticket, $direction, $method, $gate, $clientUuid) {
            if ($clientUuid && Checkin::where('ticket_id', $ticket->id)->where('client_uuid', $clientUuid)->exists()) {
                return $this->r(true, 'green', 'success', __('Déjà synchronisé.'), $ticket);
            }

            $ticket->checked_in = $direction === 'in';
            $ticket->checked_in_at = $direction === 'in' ? now() : $ticket->checked_in_at;
            $ticket->save();

            Checkin::create([
                'event_id' => $event->id, 'ticket_id' => $ticket->id, 'direction' => $direction,
                'method' => $method, 'gate' => $gate, 'client_uuid' => $clientUuid, 'scanned_at' => now(),
            ]);

            return $this->r(true, 'green', 'success', $direction === 'in' ? __('Bienvenue!') : __('Sortie enregistrée.'), $ticket->fresh('ticketType'));
        });
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
