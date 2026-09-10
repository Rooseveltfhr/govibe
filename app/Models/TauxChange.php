<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TauxChange extends Model
{
    protected $table = 'taux_change';

    protected $fillable = [
        'devise_source', 'devise_cible', 'taux', 'defini_par', 'applique_depuis',
    ];

    protected $casts = [
        'taux' => 'decimal:6',
        'applique_depuis' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'defini_par');
    }

    /**
     * Taux en vigueur, ou null s'il n'a jamais été réglé.
     *
     * Rien n'est deviné : sans taux saisi par le super admin, la conversion
     * n'est pas affichée. Un taux inventé se traduirait en écart de caisse.
     */
    public static function actuel(string $source, string $cible): ?float
    {
        if ($source === $cible) {
            return 1.0;
        }

        $direct = static::where('devise_source', $source)
            ->where('devise_cible', $cible)
            ->where('applique_depuis', '<=', now())
            ->latest('applique_depuis')
            ->value('taux');

        if ($direct !== null) {
            return (float) $direct;
        }

        // Le taux inverse fait foi aussi : saisir USD→HTG suffit.
        $inverse = static::where('devise_source', $cible)
            ->where('devise_cible', $source)
            ->where('applique_depuis', '<=', now())
            ->latest('applique_depuis')
            ->value('taux');

        return $inverse !== null && (float) $inverse != 0.0
            ? round(1 / (float) $inverse, 6)
            : null;
    }
}
