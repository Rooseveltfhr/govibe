<?php

namespace App\Models\Etablissements;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'establishment_id', 'guest_name', 'guest_phone', 'guest_email',
        'starts_on', 'ends_on', 'reservation_time', 'party_size', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
