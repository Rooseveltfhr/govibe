<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA LOYALTY — carte NFC de fidélité.
 *
 * Sécurité : le numéro complet (card_number) n'est jamais exposé publiquement.
 * L'URL publique utilise public_token. card_number_encrypted permet de restituer
 * le n° au owner si besoin ; cvc est hashé (vérification, pas affichage).
 *
 * @property int    $id
 * @property int    $program_id
 * @property string $public_token
 * @property string $card_number
 * @property string $cvc
 * @property string $cardholder_name
 * @property float  $balance
 * @property int    $points
 * @property int    $status
 */
class TaGtoaLoyaltyCard extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE    = 1;
    public const STATUS_SUSPENDED = 0;
    public const STATUS_EXPIRED   = 2;

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE    => 'Active',
        self::STATUS_SUSPENDED => 'Suspendue',
        self::STATUS_EXPIRED   => 'Expirée',
    ];

    public const DELIVERY_PICKUP     = 0;
    public const DELIVERY_HOME       = 1;
    public const DELIVERY_AUTH_POINT = 2;

    protected $table = 'tagtoa_loyalty_cards';

    protected $fillable = [
        'program_id', 'public_token', 'card_number', 'card_number_encrypted', 'cvc',
        'expiry_date', 'cardholder_name', 'cardholder_phone', 'cardholder_email',
        'balance', 'points', 'status', 'delivery_type', 'delivery_address', 'issued_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'issued_at'   => 'datetime',
        'balance'     => 'decimal:2',
        'points'      => 'integer',
        'status'      => 'integer',
        'delivery_type' => 'integer',
    ];

    // Jamais sérialiser les secrets.
    protected $hidden = ['card_number', 'card_number_encrypted', 'cvc'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TaGtoaLoyaltyProgram::class, 'program_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TaGtoaLoyaltyTransaction::class, 'card_id')->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Inconnu';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /** Numéro masqué pour affichage public : **** **** **** 1234. */
    public function getMaskedNumberAttribute(): string
    {
        $last = substr($this->card_number, -4);
        return '•••• •••• •••• ' . $last;
    }

    /** Numéro formaté par groupes de 4 (usage owner uniquement). */
    public function getFormattedNumberAttribute(): string
    {
        return trim(chunk_split($this->card_number, 4, ' '));
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/loyalty/card/' . $this->public_token);
    }
}
