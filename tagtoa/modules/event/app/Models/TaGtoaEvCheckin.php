<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — enregistrement de check-in / check-out.
 */
class TaGtoaEvCheckin extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_ev_checkins';

    protected $fillable = [
        'event_id', 'ticket_id', 'direction', 'method', 'gate', 'client_uuid', 'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvTicket::class, 'ticket_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvent::class, 'event_id');
    }
}
