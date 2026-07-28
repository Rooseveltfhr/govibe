<?php

namespace Modules\Tagtoa\App\Models\Loyalty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA Loyalty — carte NFC. Numéro masqué en public ; CVC hashé ; URL via token.
 */
class Card extends Model
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_SUSPENDED = 0;
    public const STATUS_EXPIRED = 2;

    public const STATUS_LABELS = [1 => 'Active', 0 => 'Suspendue', 2 => 'Expirée'];

    protected $table = 'tagtoa_loyalty_cards';

    protected $fillable = [
        'program_id', 'public_token', 'card_number', 'card_number_encrypted', 'cvc',
        'expiry_date', 'cardholder_name', 'cardholder_phone', 'cardholder_email',
        'balance', 'points', 'status', 'issued_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'issued_at'   => 'datetime',
        'balance'     => 'decimal:2',
        'points'      => 'integer',
        'status'      => 'integer',
    ];

    protected $hidden = ['card_number', 'card_number_encrypted', 'cvc'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'card_id')->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? '—';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! ($this->expiry_date && $this->expiry_date->isPast());
    }

    public function getMaskedNumberAttribute(): string
    {
        return '•••• •••• •••• '.substr((string) $this->card_number, -4);
    }

    public function getFormattedNumberAttribute(): string
    {
        return trim(chunk_split((string) $this->card_number, 4, ' '));
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/loyalty/card/'.$this->public_token);
    }
}
