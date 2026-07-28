<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — compte staff terrain, scopé par événement.
 * Auth par PIN (voir StaffPinService). `pin_hash` masqué à la sérialisation.
 */
class Staff extends Model
{
    protected $table = 'tagtoa_ev_staff';

    protected $fillable = ['event_id', 'name', 'pin_hash', 'role', 'active', 'created_by', 'last_login_at'];

    protected $casts = ['active' => 'boolean', 'last_login_at' => 'datetime'];

    protected $hidden = ['pin_hash'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
