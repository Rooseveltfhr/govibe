<?php

namespace Modules\AIProvider\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * État opérationnel d'un fournisseur (activation, priorité). Le nom de classe
 * évite la collision avec le contrat `AIProvider`.
 *
 * @property string $key
 * @property string $status
 */
class AiProviderRecord extends Model
{
    protected $table = 'ai_providers';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** @return HasMany<AiModelRecord, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(AiModelRecord::class, 'ai_provider_id');
    }
}
