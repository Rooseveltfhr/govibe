<?php

namespace Modules\Agents\DTO;

/**
 * Yon mesaj ki antre, kèlkeswa kanal la.
 *
 * Se pyès ki fè « yon sèl ajan, plizyè kanal » posib: WhatsApp, telefòn,
 * widget sou sit, API — yo tout tradui sa yo resevwa nan fòm sa a.
 * Runtime lan pa janm konnen ki kanal mesaj la soti.
 */
final readonly class IncomingMessage
{
    /**
     * @param  string  $channel  kle kanal la: 'whatsapp', 'phone', 'web', 'api'
     * @param  string  $conversationRef  idantifyan konvèsasyon an bò kanal la
     * @param  string  $text  tèks la (deja transkri si li te vwa)
     * @param  array<string, mixed>  $meta  done pwòp kanal la (nimewo, id apèl…)
     */
    public function __construct(
        public string $channel,
        public string $conversationRef,
        public string $text,
        public ?string $audioPath = null,
        public ?string $language = null,
        public array $meta = [],
    ) {}

    public function wasSpoken(): bool
    {
        return $this->audioPath !== null;
    }
}
