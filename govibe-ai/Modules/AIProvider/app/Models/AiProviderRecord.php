<?php

namespace Modules\AIProvider\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * État opérationnel d'un fournisseur (activation, priorité). Le nom de classe
 * évite la collision avec le contrat `AIProvider`.
 *
 * @property string $key
 * @property string $name
 * @property string $status
 * @property string|null $api_key chiffre nan baz la — jamè an klè
 */
class AiProviderRecord extends Model
{
    protected $table = 'ai_providers';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            // Chiffre: yon moun ki li tab la pa jwenn kle a san APP_KEY la.
            'api_key' => 'encrypted',
        ];
    }

    /** @return HasMany<AiModelRecord, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(AiModelRecord::class, 'ai_provider_id');
    }
}
