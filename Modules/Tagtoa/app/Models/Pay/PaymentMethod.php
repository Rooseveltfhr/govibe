<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * TAGTOA Pay — méthode de paiement d'une page. QR stocké en fichier (qr_path),
 * pas de dépendance medialibrary (volontairement simple).
 */
class PaymentMethod extends Model
{
    protected $table = 'tagtoa_payment_methods';

    protected $fillable = [
        'payment_page_id', 'type', 'label', 'account_holder', 'account_number',
        'instructions', 'qr_path', 'requires_proof', 'is_active', 'sort',
    ];

    protected $casts = [
        'requires_proof' => 'boolean',
        'is_active'      => 'boolean',
        'sort'           => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'payment_page_id');
    }

    public function getMetaAttribute(): array
    {
        return PaymentPage::METHODS[$this->type]
            ?? ['label' => ucfirst($this->type), 'icon' => 'fa-solid fa-money-check-dollar', 'region' => 'other'];
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
        return $this->qr_path ? Storage::url($this->qr_path) : null;
    }
}
