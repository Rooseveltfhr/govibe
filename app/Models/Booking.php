<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'title',
        'space_id',
        'client_id',
        'user_id',
        'start_datetime',
        'end_datetime',
        'total_price',
        'status',
        'payment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'total_price' => 'decimal:2',
        ];
    }

    /**
     * Référence lisible, unique. La colonne est déclarée unique en base :
     * on réessaie tant que le tirage est déjà pris.
     */
    public static function genererReference(): string
    {
        do {
            $reference = 'BK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Réservations qui occupent déjà l'espace sur ce créneau.
     *
     * Deux créneaux se chevauchent si chacun commence avant que l'autre ne
     * finisse. Les bornes sont strictes : une réservation qui finit à 10h
     * n'empêche pas celle qui commence à 10h.
     */
    public function scopeChevauche(Builder $query, int $spaceId, $debut, $fin): Builder
    {
        // Normalisation obligatoire : une chaîne « 2026-10-01 11:00 » comparée
        // telle quelle à la valeur stockée « 2026-10-01 11:00:00 » se compare
        // comme du texte, et le plus long l'emporte — un créneau contigu
        // était alors vu comme un chevauchement.
        $debut = Carbon::parse($debut);
        $fin   = Carbon::parse($fin);

        return $query->where('space_id', $spaceId)
            ->whereNotIn('status', ['cancelled'])
            ->where('start_datetime', '<', $fin)
            ->where('end_datetime', '>', $debut);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
