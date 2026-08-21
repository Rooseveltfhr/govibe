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
    /** Billet pré-imprimé importé, enregistré mais pas encore vendu/activé. */
    public const STATUS_UNSOLD = 2;

    protected $table = 'tagtoa_ev_tickets';

    protected $fillable = [
        'event_id', 'order_id', 'ticket_type_id', 'code', 'serial', 'imported', 'holder_name', 'holder_phone',
        'payment_method', 'sold_by_staff_id', 'sold_at', 'status', 'checked_in', 'checked_in_at',
    ];

    protected $casts = [
        'status' => 'integer', 'checked_in' => 'boolean', 'checked_in_at' => 'datetime',
        'imported' => 'boolean', 'sold_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id');
    }

    public function soldByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'sold_by_staff_id');
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

    /** Billet pré-imprimé enregistré mais pas encore vendu (non scannable à l'entrée). */
    public function isUnsold(): bool
    {
        return $this->status === self::STATUS_UNSOLD;
    }
}
