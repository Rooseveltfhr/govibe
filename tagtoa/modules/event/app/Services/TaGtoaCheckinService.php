<?php

namespace App\Services;

use App\Models\TaGtoaEvCheckin;
use App\Models\TaGtoaEvTicket;
use App\Models\TaGtoaEvent;
use Illuminate\Support\Facades\DB;

/**
 * TAGTOA EVENT — moteur de check-in (scanner PWA).
 *
 * processScan() renvoie un tableau JSON-able standard utilisé par le scanner :
 *   ['valid' => bool, 'color' => 'green|red|orange', 'sound' => 'success|error|warning',
 *    'message' => string, 'ticket' => array|null]
 *
 * Offline-first : le scanner peut bufferiser les scans (client_uuid) et appeler
 * sync() plus tard ; la déduplication se fait sur (ticket_id, client_uuid).
 */
class TaGtoaCheckinService
{
    public function processScan(
        TaGtoaEvent $event,
        string $code,
        string $direction = 'in',
        string $method = 'qr',
        ?string $gate = null,
        ?string $clientUuid = null
    ): array {
        $ticket = TaGtoaEvTicket::where('event_id', $event->id)
            ->where('code', $code)
            ->with('ticketType')
            ->first();

        if (! $ticket) {
            return $this->result(false, 'red', 'error', __('Billet introuvable.'));
        }

        if (! $ticket->isValid()) {
            return $this->result(false, 'red', 'error', __('Billet annulé.'), $ticket);
        }

        // Anti double-entrée : déjà à l'intérieur et on re-scanne une entrée.
        if ($direction === 'in' && $ticket->checked_in) {
            return $this->result(
                false,
                'orange',
                'warning',
                __('Déjà entré à :time', ['time' => optional($ticket->checked_in_at)->format('H:i')]),
                $ticket
            );
        }
        if ($direction === 'out' && ! $ticket->checked_in) {
            return $this->result(false, 'orange', 'warning', __('Pas encore entré.'), $ticket);
        }

        return DB::transaction(function () use ($event, $ticket, $direction, $method, $gate, $clientUuid) {
            // Idempotence offline.
            if ($clientUuid) {
                $dup = TaGtoaEvCheckin::where('ticket_id', $ticket->id)
                    ->where('client_uuid', $clientUuid)->first();
                if ($dup) {
                    return $this->result(true, 'green', 'success', __('Déjà synchronisé.'), $ticket);
                }
            }

            $ticket->checked_in    = $direction === 'in';
            $ticket->checked_in_at = $direction === 'in' ? now() : $ticket->checked_in_at;
            $ticket->save();

            TaGtoaEvCheckin::create([
                'event_id'    => $event->id,
                'ticket_id'   => $ticket->id,
                'direction'   => $direction,
                'method'      => $method,
                'gate'        => $gate,
                'client_uuid' => $clientUuid,
                'scanned_at'  => now(),
            ]);

            $label = $direction === 'in' ? __('Bienvenue!') : __('Sortie enregistrée.');
            return $this->result(true, 'green', 'success', $label, $ticket->fresh('ticketType'));
        });
    }

    /** Traite un lot de scans bufferisés hors-ligne. */
    public function sync(TaGtoaEvent $event, array $scans): array
    {
        $results = [];
        foreach ($scans as $s) {
            $results[] = [
                'client_uuid' => $s['client_uuid'] ?? null,
                'result'      => $this->processScan(
                    $event,
                    $s['code'] ?? '',
                    $s['direction'] ?? 'in',
                    $s['method'] ?? 'qr',
                    $s['gate'] ?? null,
                    $s['client_uuid'] ?? null
                ),
            ];
        }
        return $results;
    }

    private function result(bool $valid, string $color, string $sound, string $message, ?TaGtoaEvTicket $ticket = null): array
    {
        return [
            'valid'   => $valid,
            'color'   => $color,
            'sound'   => $sound,
            'message' => $message,
            'ticket'  => $ticket ? [
                'code'        => $ticket->code,
                'holder'      => $ticket->holder_name,
                'type'        => optional($ticket->ticketType)->name,
                'checked_in'  => $ticket->checked_in,
                'wallet'      => (float) $ticket->wallet_balance,
            ] : null,
        ];
    }
}
