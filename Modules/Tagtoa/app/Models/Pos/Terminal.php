<?php

namespace Modules\Tagtoa\App\Models\Pos;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA POS — caisse / terminal.
 */
class Terminal extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_pos_terminals';

    protected $fillable = ['vcard_id', 'tenant_id', 'name', 'currency', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Garde-fou : supprimer une caisse ne doit pas emporter le catalogue.
     *
     * La clé étrangère `tagtoa_pos_products.terminal_id` supprime encore en
     * cascade. Tant que le catalogue appartenait à la caisse, c'était correct ;
     * il est maintenant PARTAGÉ, donc supprimer une caisse effacerait les
     * articles de tout le commerce. Avant de partir, la caisse repasse donc ses
     * articles à une autre caisse du même commerce.
     *
     * Aucune route ne permet cette suppression aujourd'hui — ce garde-fou existe
     * pour le jour où elle sera ajoutée.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $terminal) {
            $repli = static::where('tenant_id', $terminal->tenant_id)
                ->whereKeyNot($terminal->getKey())->first();

            if ($repli) {
                Product::where('terminal_id', $terminal->getKey())
                    ->update(['terminal_id' => $repli->getKey()]);
            }
        });
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class, 'vcard_id');
    }

    /**
     * Catalogue du COMMERCE, partagé par toutes ses caisses.
     *
     * La relation porte sur `tenant_id`, pas sur la caisse : deux caisses d'un
     * même commerce voient exactement les mêmes articles. Le nom est conservé
     * pour que le code existant continue de fonctionner.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id', 'tenant_id')->orderBy('sort');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'terminal_id');
    }
}
