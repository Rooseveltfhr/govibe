<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * TAGTOA PAY — preuve de paiement soumise par un client.
 *
 * @property int      $id
 * @property int      $payment_page_id
 * @property int      $payment_method_id
 * @property string   $payer_name
 * @property string   $payer_phone
 * @property float    $amount
 * @property string   $currency
 * @property string   $reference
 * @property int      $status
 * @property string   $note
 * @property \Illuminate\Support\Carbon $reviewed_at
 */
class TaGtoaPaymentProof extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public const STATUS_PENDING  = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    public const STATUS_LABELS = [
        self::STATUS_PENDING  => 'En attente',
        self::STATUS_APPROVED => 'Approuvé',
        self::STATUS_REJECTED => 'Rejeté',
    ];

    protected $table = 'tagtoa_payment_proofs';

    protected $fillable = [
        'payment_page_id',
        'payment_method_id',
        'payer_name',
        'payer_phone',
        'amount',
        'currency',
        'reference',
        'status',
        'note',
        'reviewed_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'status'      => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof-image')->singleFile();
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPaymentPage::class, 'payment_page_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPaymentMethod::class, 'payment_method_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Inconnu';
    }

    public function getImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('proof-image');

        return $media ? $media->getUrl() : null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
