<?php

namespace Modules\Tagtoa\App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Services\Catalog\ProductCodes;
use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — à quel article correspond ce code ?
 *
 * Le navigateur lit une suite de caractères. C'est tout ce qu'il sait faire, et
 * c'est tout ce qu'on lui laisse faire : le prix, le stock et l'identité de
 * l'article viennent d'ici, jamais de la caisse.
 *
 * Le même code-barres existe chez tous les commerçants du pays — la bouteille
 * de Coca porte le même numéro partout. La recherche est donc TOUJOURS bornée
 * au commerce courant. Sans cela, scanner chez l'un donnerait le prix, le
 * stock, ou pire la marge de l'autre.
 */
class ScanController extends Controller
{
    public function __construct(protected ProductCodes $codes)
    {
    }

    /**
     * Résout un code. Réponse volontairement pauvre : ce qu'il faut pour
     * vendre, rien de plus. Le prix d'achat et la marge ne sortent jamais par
     * cette porte — elle est appelée depuis une caisse, parfois tenue par
     * quelqu'un qui n'a pas à les connaître.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $code = Barcode::normalize($data['code']);

        if (strlen($code) < 4) {
            return response()->json([
                'found'  => false,
                'reason' => 'too_short',
                'code'   => $code,
            ], 200);
        }

        $tenantId = Tenant::id();
        $article  = $this->codes->find($tenantId, $code);

        if (! $article) {
            // 200, pas 404 : ce n'est pas une erreur d'appel, c'est une réponse
            // — « ce code ne correspond à rien chez vous ». La caisse doit
            // pouvoir proposer de créer l'article sans traiter ça comme une
            // panne.
            return response()->json([
                'found'  => false,
                'reason' => 'unknown',
                'code'   => $code,
                'type'   => Barcode::typeLabel($code),
            ], 200);
        }

        $source = $article instanceof Item ? CatalogRef::SOURCE_MENU : CatalogRef::SOURCE_POS;
        $devise = Tenant::currency();

        return response()->json([
            'found'   => true,
            'code'    => $code,
            'article' => [
                'ref'         => CatalogRef::make($source, (int) $article->id),
                'source'      => $source,
                'name'        => $article->name,
                'price'       => (float) $article->price,
                'price_label' => Money::format((float) $article->price, $devise),
                'unit'        => $article->unit_key,
                'unit_label'  => $article->unit_label,
                'decimal'     => $article->allowsDecimalQty(),
                // Le stock sert à prévenir le caissier, pas à l'empêcher de
                // vendre : un stock faux ne doit jamais bloquer un client qui
                // tend son argent.
                'stock'       => $article->stock === null ? null : (float) $article->stock,
                'low'         => $article->isLowStock(),
                'out'         => $article->isOutOfStock(),
            ],
        ]);
    }
}
