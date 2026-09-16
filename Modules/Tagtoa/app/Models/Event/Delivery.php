<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA Event — une tentative de livraison de confirmation au CLIENT (achat
 * ou entrée), un canal à la fois. Voir EventNotifier.
 */
class Delivery extends Model
{
    public const CONTEXT_ORDER_CONFIRMATION = 'order_confirmation';
    public const CONTEXT_CHECKIN_CONFIRMATION = 'checkin_confirmation';

    public const STATUS_SENT = 'sent';
    public const STATUS_NOT_SENT = 'not_sent';

    protected $table = 'tagtoa_ev_deliveries';

    protected $fillable = ['event_id', 'ticket_id', 'context', 'channel', 'recipient', 'status'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
