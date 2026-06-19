<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * TAGTOA Pay — preuve de paiement soumise par un client.
 */
class PaymentProof extends Model
{
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
        'payment_page_id', 'payment_method_id', 'payer_name', 'payer_phone',
        'amount', 'currency', 'reference', 'proof_path', 'status', 'note', 'reviewed_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'status'      => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'payment_page_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? '—';
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->proof_path ? Storage::url($this->proof_path) : null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
