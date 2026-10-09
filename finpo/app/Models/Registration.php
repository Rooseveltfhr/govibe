<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Registration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(CheckinLog::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function audienceLabel(): string
    {
        return config('finpo.attendee_categories.'.$this->audience.'.label', ucfirst($this->audience));
    }

    public function badgeColor(): string
    {
        return config('finpo.attendee_categories.'.$this->audience.'.color', '#334155');
    }

    public function isPaid(): bool
    {
        return in_array($this->payment_status, ['paid', 'free'], true);
    }

    /** Génère un numéro de billet unique, ex. FINPO26-004217. */
    public static function nextNumber(): string
    {
        do {
            $number = 'FINPO26-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('number', $number)->exists());

        return $number;
    }

    public static function newQrToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (static::where('qr_token', $token)->exists());

        return $token;
    }
}
