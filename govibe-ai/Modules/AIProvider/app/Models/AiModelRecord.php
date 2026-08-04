<?php

namespace Modules\AIProvider\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surcharge en base du catalogue déclaré par les manifestes : prix, qualité,
 * activation — modifiables depuis l'administration sans déploiement.
 *
 * @property string $provider_key
 * @property string $key
 * @property bool $is_active
 */
class AiModelRecord extends Model
{
    protected $table = 'ai_models';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'quality_by_language' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<AiProviderRecord, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProviderRecord::class, 'ai_provider_id');
    }
}
