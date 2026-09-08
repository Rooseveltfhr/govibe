<?php

namespace App\Models;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Activité/expérience touristique (brief §6 : "/explorer", fiche + contact
 * direct, sans moteur de réservation en ligne — même logique que Establishment).
 */
class Activity extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'title', 'slug', 'category', 'description', 'duration', 'price_range', 'phone', 'whatsapp',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
