<?php

namespace Modules\Tagtoa\App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\Api\ApiToken;

/**
 * TAGTOA API — clé d'accès développeur. Le secret n'est jamais lisible :
 * seul son SHA-256 est stocké, et `key_hash` est masqué à la sérialisation.
 */
class ApiKey extends Model
{
    protected $table = 'tagtoa_api_keys';

    protected $fillable = [
        'tenant_id', 'name', 'key_prefix', 'key_hash',
        'webhook_url', 'webhook_secret', 'last_used_at', 'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    protected $hidden = ['key_hash', 'webhook_secret'];

    public function payments(): HasMany
    {
        return $this->hasMany(ApiPayment::class, 'api_key_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function getMaskedKeyAttribute(): string
    {
        return ApiToken::mask((string) $this->key_prefix);
    }
}
