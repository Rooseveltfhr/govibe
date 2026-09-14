<?php

namespace Modules\Tagtoa\App\Services\Catalog;

use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Catalog\ProductCode;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;

/**
 * TAGTOA — retrouver un article par son code, et lui en attribuer.
 *
 * C'est le chemin qu'empruntera le scanner. Deux exigences le gouvernent :
 *
 *   • la recherche est TOUJOURS limitée au commerce. Un code n'identifie pas un
 *     produit dans l'absolu : la même bouteille de Coca porte le même code chez
 *     tout le monde. Scanner chez l'un ne doit jamais donner l'article, le prix
 *     ou le stock de l'autre ;
 *   • un code refusé vaut mieux qu'un code deviné. Un chiffre mal lu ne doit
 *     pas désigner un article voisin — ce serait encaisser le mauvais prix.
 */
class ProductCodes
{
    /**
     * Décalage des articles du MENU dans les codes TAGTOA fabriqués.
     *
     * Les deux catalogues numérotent à partir de 1 : sans décalage, le plat n°7
     * et le bouton n°7 recevraient le même code interne, donc scanner l'un
     * donnerait l'autre. Le menu occupe la plage haute.
     */
    private const MENU_OFFSET = 500000;

    public function __construct(protected PosCatalog $catalog)
    {
    }

    /** Numéro porté par un code TAGTOA, pour un article donné. PUR. */
    private function internalNumber(string $source, int $id): int
    {
        return $source === CatalogRef::SOURCE_MENU ? self::MENU_OFFSET + $id : $id;
    }

    /** Le chemin inverse : de quel article vient ce numéro. PUR. */
    private function refFromInternalNumber(int $numero): string
    {
        return $numero > self::MENU_OFFSET
            ? CatalogRef::make(CatalogRef::SOURCE_MENU, $numero - self::MENU_OFFSET)
            : CatalogRef::make(CatalogRef::SOURCE_POS, $numero);
    }

    /**
     * Article portant ce code DANS ce commerce, ou null.
     *
     * Deux chemins : le code enregistré, et le code interne fabriqué par
     * TAGTOA, qui porte lui-même le numéro de l'article.
     */
    public function find(?string $tenantId, ?string $code): Product|Item|null
    {
        $code = Barcode::normalize($code);
        if ($code === '') {
            return null;
        }

        $enregistre = ProductCode::where('tenant_id', $tenantId)->where('code', $code)->first();
        if ($enregistre) {
            return $this->catalog->resolve($tenantId, $enregistre->ref);
        }

        // Code TAGTOA jamais enregistré (étiquette rééditée, base restaurée) :
        // il porte son numéro d'article, on peut donc retomber dessus — à
        // condition de défaire le décalage, sinon un code du menu désignerait
        // un bouton de caisse.
        $numero = Barcode::internalProductId($code);
        if ($numero !== null) {
            return $this->catalog->resolve($tenantId, $this->refFromInternalNumber($numero));
        }

        return null;
    }

    /** Codes d'un article, le principal d'abord. */
    public function forArticle(?string $tenantId, string $ref): Collection
    {
        $parsed = CatalogRef::parse($ref);
        if ($parsed === null) {
            return collect();
        }

        [$source, $id] = $parsed;

        return ProductCode::where('tenant_id', $tenantId)
            ->where('source', $source)->where('product_id', $id)
            ->orderByDesc('is_primary')->orderBy('id')->get();
    }

    /**
     * Attribue un code à un article.
     *
     * Renvoie null si le code est refusé — chiffre de contrôle faux, trop
     * court — ou s'il désigne DÉJÀ un autre article du commerce. Dans ce
     * dernier cas on ne réaffecte rien en silence : deux articles qui
     * partagent un code rendraient le scan aléatoire, et c'est au marchand de
     * dire lequel le garde.
     */
    public function attach(?string $tenantId, string $ref, ?string $code, array $options = []): ?ProductCode
    {
        $code = Barcode::normalize($code);
        $parsed = CatalogRef::parse($ref);

        if ($parsed === null || ! Barcode::isAcceptable($code)) {
            return null;
        }

        [$source, $id] = $parsed;

        $existant = ProductCode::where('tenant_id', $tenantId)->where('code', $code)->first();
        if ($existant) {
            $memeArticle = $existant->source === $source && (int) $existant->product_id === $id;

            return $memeArticle ? $existant : null;
        }

        return ProductCode::create([
            'tenant_id'  => $tenantId,
            'source'     => $source,
            'product_id' => $id,
            'code'       => $code,
            'type'       => Barcode::typeOf($code),
            'label'      => $options['label'] ?? null,
            'is_primary' => (bool) ($options['is_primary'] ?? ! $this->forArticle($tenantId, $ref)->count()),
        ]);
    }

    /**
     * Fabrique et attribue un code TAGTOA à un article qui n'en a pas.
     *
     * C'est ce qui rend le scanner utile en Haïti : la majorité de ce que vend
     * une boutique de quartier — pâté, fresco, sachet dlo, manje kwit — ne
     * porte aucun code imprimé. Le commerce imprime l'étiquette et scanne
     * ensuite comme pour n'importe quel produit industriel.
     */
    public function generate(?string $tenantId, string $ref, ?string $label = null): ?ProductCode
    {
        $parsed = CatalogRef::parse($ref);
        if ($parsed === null) {
            return null;
        }

        [$source, $id] = $parsed;

        return $this->attach($tenantId, $ref, Barcode::internal($this->internalNumber($source, $id)), [
            'label'      => $label ?? __('Code TAGTOA'),
            'is_primary' => ! $this->forArticle($tenantId, $ref)->count(),
        ]);
    }

    /** Retire un code. Le marchand reste maître de ses étiquettes. */
    public function detach(?string $tenantId, int $codeId): bool
    {
        return (bool) ProductCode::where('tenant_id', $tenantId)->whereKey($codeId)->delete();
    }
}
