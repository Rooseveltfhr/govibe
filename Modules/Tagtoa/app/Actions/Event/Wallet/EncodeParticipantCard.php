<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;

/**
 * TAGTOA EVENT — encode une carte NFC pour un participant : émet un BILLET
 * (pour l'accès/check-in) ET relie la carte à un WALLET (via IssueNfcTag).
 *
 * C'est l'étape « point de vente » : le staff tape une carte vierge, saisit
 * le nom + téléphone + type de billet, et la carte est prête (entrée + paiement).
 * Réutilisable : une même carte physique peut être ré-encodée pour un AUTRE
 * événement (les tags sont scopés par event).
 *
 * @return array{ticket: Ticket, tag: \Modules\Tagtoa\App\Models\Event\NfcTag}
 */
class EncodeParticipantCard
{
    public function __construct(protected IssueNfcTag $issue)
    {
    }

    public function handle(Event $event, array $data): array
    {
        return DB::transaction(function () use ($event, $data) {
            $ticket = Ticket::create([
                'event_id'       => $event->id,
                'order_id'       => null,
                'ticket_type_id' => $data['ticket_type_id'] ?? null,
                'code'           => Ticket::generateCode(),
                'holder_name'    => $data['name'] ?? null,
                'holder_phone'   => $data['phone'] ?? null,
                'status'         => Ticket::STATUS_VALID,
            ]);

            $tag = $this->issue->handle($event, $data['uid'], [
                'label'     => $data['name'] ?? null,
                'phone'     => $data['phone'] ?? null,
                'email'     => $data['email'] ?? null,
                'ticket_id' => $ticket->id,
                'kind'      => $data['kind'] ?? 'card',
            ]);

            return ['ticket' => $ticket, 'tag' => $tag];
        });
    }
}
