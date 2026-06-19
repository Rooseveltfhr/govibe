<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA Event — enregistrement de check-in / check-out.
 */
class Checkin extends Model
{
    protected $table = 'tagtoa_ev_checkins';

    protected $fillable = ['event_id', 'ticket_id', 'direction', 'method', 'gate', 'client_uuid', 'scanned_at'];

    protected $casts = ['scanned_at' => 'datetime'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
