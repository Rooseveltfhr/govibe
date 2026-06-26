<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\PaymentGateway;

/**
 * TAGTOA Pay — méthode de paiement d'une page. QR stocké en fichier (qr_path),
 * pas de dépendance medialibrary (volontairement simple).
 */
class PaymentMethod extends Model
{
    protected $table = 'tagtoa_payment_methods';

    protected $fillable = [
        'payment_page_id', 'type', 'label', 'account_holder', 'institution', 'account_number',
        'instructions', 'qr_path', 'logo_path', 'requires_proof', 'is_active', 'sort',
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

    /** Métadonnées passerelle : label, icon, mode (auto/manual), kind, color, driver. */
    public function getMetaAttribute(): array
    {
        return PaymentGateway::meta($this->type);
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: $this->meta['label'];
    }

    public function getIconAttribute(): string
    {
        return $this->meta['icon'];
    }

    public function getBrandColorAttribute(): string
    {
        return $this->meta['color'] ?? '#0055FF';
    }

    /** Cette méthode est-elle une passerelle automatique (API) ? */
    public function isAuto(): bool
    {
        return PaymentGateway::isAuto($this->type);
    }

    /** Peut-on régler en ligne maintenant (driver configuré) ? */
    public function onlineAvailable(): bool
    {
        return GatewayManager::onlineAvailable($this->type);
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->qr_path ? Storage::url($this->qr_path) : null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }
}
