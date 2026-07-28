<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * TAGTOA Event — billet émis (QR + NFC).
 */
class Ticket extends Model
{
    public const STATUS_VALID = 1;
    public const STATUS_VOID = 0;

    protected $table = 'tagtoa_ev_tickets';

    protected $fillable = [
        'event_id', 'order_id', 'ticket_type_id', 'code', 'holder_name', 'holder_phone', 'payment_method',
        'status', 'checked_in', 'checked_in_at',
    ];

    protected $casts = ['status' => 'integer', 'checked_in' => 'boolean', 'checked_in_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public static function generateCode(): string
    {
        do {
            $code = 'T'.strtoupper(Str::random(11));
        } while (static::where('code', $code)->exists());
        return $code;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }
}
