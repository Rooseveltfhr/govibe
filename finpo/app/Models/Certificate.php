<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public static function nextNumber(): string
    {
        do {
            $number = 'FINPO26-CERT-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('number', $number)->exists());

        return $number;
    }
}
