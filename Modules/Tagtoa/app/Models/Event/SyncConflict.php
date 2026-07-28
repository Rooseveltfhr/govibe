<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — conflit de synchronisation offline (ex. double check-in).
 */
class SyncConflict extends Model
{
    protected $table = 'tagtoa_ev_sync_conflicts';

    protected $fillable = ['event_id', 'kind', 'client_uuid', 'ticket_id', 'staff_id', 'payload', 'resolved'];

    protected $casts = ['payload' => 'array', 'resolved' => 'boolean'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
