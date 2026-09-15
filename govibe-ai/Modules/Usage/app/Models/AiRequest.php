<?php

namespace Modules\Usage\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $uuid
 * @property string $capability
 * @property string $provider_key
 * @property string $model_key
 * @property string $status
 */
class AiRequest extends Model
{
    protected $table = 'ai_requests';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'streamed' => 'boolean',
        ];
    }
}
