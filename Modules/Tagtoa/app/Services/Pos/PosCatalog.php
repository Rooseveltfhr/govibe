<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;

/**
 * TAGTOA POS — le catalogue du COMMERCE, partagé par toutes ses caisses.
 *
 * Le catalogue appartenait à la caisse : un commerce à deux caisses saisissait
 * ses produits deux fois, et un article créé sur la caisse 1 restait
 * introuvable depuis la caisse 2.
 *
 * Point de lecture UNIQUE : toute recherche de produit passe par ici, toujours
 * limitée au commerce. C'est ce qui rendra le scan de code-barres correct — un
 * code identifie un produit du commerce, jamais celui du voisin.
 */
class PosCatalog
{
    /** Requête de base : le catalogue du commerce, dans l'ordre d'affichage. */
    public function query(?string $tenantId): Builder
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')->orderBy('id');
    }

    /** Tout le catalogue, y compris les articles retirés de la vente. */
    public function all(?string $tenantId): Collection
    {
        return $this->query($tenantId)->get();
    }

    /** Articles proposés à la vente. */
    public function active(?string $tenantId): Collection
    {
        return $this->query($tenantId)->where('is_active', true)->get();
    }

    /**
     * Un article du catalogue de CE commerce, ou null.
     *
     * Jamais `Product::find()` : un identifiant deviné donnerait l'article d'un
     * autre commerce. C'est ce chemin que le scanner utilisera demain.
     */
    public function find(?string $tenantId, int $productId): ?Product
    {
        return $this->query($tenantId)->whereKey($productId)->first();
    }

    /**
     * Enregistre un article. `terminal_id` reste renseigné : la colonne est
     * obligatoire en base et garde la trace de la caisse de saisie.
     */
    public function save(Terminal $terminal, array $attributes, ?int $productId = null): Product
    {
        $existing = $productId ? $this->find($terminal->tenant_id, $productId) : null;

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return Product::create($attributes + [
            'tenant_id'   => $terminal->tenant_id,
            'terminal_id' => $terminal->id,
        ]);
    }
}
