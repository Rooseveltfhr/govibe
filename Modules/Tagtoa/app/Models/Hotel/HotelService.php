<?php

namespace Modules\Tagtoa\App\Models\Hotel;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Hotel\HotelServiceRules;

/**
 * TAGTOA HÔTEL — un service proposé aux clients de l'hôtel. Isolé par commerce ; jamais
 * supprimé (désactivé). `translations` = noms par langue du portail (ht/fr/en/es).
 */
class HotelService extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_hotel_services';

    protected $fillable = ['tenant_id', 'kind', 'name', 'translations', 'description', 'price', 'team', 'menu_id', 'sort', 'is_active'];

    protected $casts = ['translations' => 'array', 'price' => 'decimal:2', 'is_active' => 'boolean', 'sort' => 'integer'];

    public function getKindLabelAttribute(): string
    {
        return HotelServiceRules::KINDS[$this->kind]['label'] ?? $this->kind;
    }

    public function getIconAttribute(): string
    {
        return HotelServiceRules::KINDS[$this->kind]['icon'] ?? 'fa-bell-concierge';
    }

    /** Nom dans la langue demandée, sinon le nom de base. */
    public function nameFor(?string $locale): string
    {
        $t = $this->translations[$locale ?? ''] ?? null;

        return is_string($t) && $t !== '' ? $t : $this->name;
    }
}
