<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA MENU — une table du commerce, identifiée par un code que seul un
 * QR/NFC imprimé peut porter (jamais tapé par le client). Voir la migration
 * pour le raisonnement complet.
 */
class Table extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_menu_tables';

    protected $fillable = ['menu_id', 'tenant_id', 'label', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /** Code court, imprononçable à deviner — jamais généré à partir du numéro de table. */
    public static function generateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
