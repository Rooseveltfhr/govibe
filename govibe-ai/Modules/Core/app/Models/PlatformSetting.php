<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Yon paramèt platfòm.
 *
 * Valè sekrè yo chiffre nan baz la (`encrypted` cast). Sa vle di yon moun ki
 * li tab la pa jwenn kle a — li bezwen APP_KEY la tou. Se pa pafè, men se
 * lwen pi bon pase tèks an klè, epi li respekte règ la: yon kredansyèl pa
 * janm ekri an klè.
 *
 * @property string $key
 * @property string|null $value
 * @property bool $is_secret
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'is_secret' => 'boolean',
        ];
    }
}
