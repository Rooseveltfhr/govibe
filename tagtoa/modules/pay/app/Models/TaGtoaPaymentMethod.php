<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * TAGTOA PAY — méthode de paiement rattachée à une page.
 *
 * @property int    $id
 * @property int    $payment_page_id
 * @property string $type
 * @property string $label
 * @property string $account_holder
 * @property string $account_number
 * @property string $instructions
 * @property bool   $requires_proof
 * @property bool   $is_active
 * @property int    $sort
 */
class TaGtoaPaymentMethod extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'tagtoa_payment_methods';

    protected $fillable = [
        'payment_page_id',
        'type',
        'label',
        'account_holder',
        'account_number',
        'instructions',
        'requires_proof',
        'is_active',
        'sort',
    ];

    protected $casts = [
        'requires_proof' => 'boolean',
        'is_active'      => 'boolean',
        'sort'           => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('payment-qr')->singleFile();
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPaymentPage::class, 'payment_page_id');
    }

    /** Métadonnées (label/icône) de ce type de paiement. */
    public function getMetaAttribute(): array
    {
        return TaGtoaPaymentPage::PAYMENT_METHODS[$this->type]
            ?? ['label' => ucfirst($this->type), 'icon' => 'fa-money-check-dollar', 'region' => 'other'];
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: $this->meta['label'];
    }

    public function getIconAttribute(): string
    {
        return $this->meta['icon'];
    }

    public function getQrUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('payment-qr');

        return $media ? $media->getUrl() : null;
    }
}
