<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA EVENT — billet émis (QR + NFC) avec wallet in-event.
 */
class TaGtoaEvTicket extends Model
{
    use HasFactory;

    public const STATUS_VALID = 1;
    public const STATUS_VOID  = 0;
    public const STATUS_USED  = 2;

    protected $table = 'tagtoa_ev_tickets';

    protected $fillable = [
        'event_id', 'order_id', 'ticket_type_id', 'code', 'holder_name',
        'holder_phone', 'status', 'checked_in', 'checked_in_at', 'wallet_balance',
    ];

    protected $casts = [
        'status'         => 'integer',
        'checked_in'     => 'boolean',
        'checked_in_at'  => 'datetime',
        'wallet_balance' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvent::class, 'event_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvOrder::class, 'order_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvTicketType::class, 'ticket_type_id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(TaGtoaEvCheckin::class, 'ticket_id');
    }

    public static function generateCode(): string
    {
        do {
            $code = 'T' . strtoupper(Str::random(11));
        } while (static::where('code', $code)->exists());
        return $code;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/event/ticket/' . $this->code);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }
}
