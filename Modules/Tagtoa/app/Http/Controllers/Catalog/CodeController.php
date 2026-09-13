<?php

namespace Modules\Tagtoa\App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Catalog\ProductCodes;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — les codes d'un article, côté patron.
 *
 * Deux réalités cohabitent dans un commerce haïtien :
 *
 *   • les produits industriels portent déjà un code imprimé : on le scanne
 *     une fois et l'article est reconnu pour toujours ;
 *   • une grande partie de ce qui se vend n'en a AUCUN — pâté, fresco, sachet
 *     dlo, manje kwit, artisanat. Pour ceux-là, TAGTOA fabrique une étiquette
 *     que le commerce imprime et rescanne comme n'importe quel produit.
 *
 * Sans le second cas, le scanner ne servirait qu'aux boutiques qui vendent des
 * marques — c'est-à-dire pas à la majorité de ceux pour qui TAGTOA est fait.
 */
class CodeController extends Controller
{
    public function __construct(protected ProductCodes $codes, protected PosCatalog $catalog)
    {
    }

    /** Les codes d'un article, et de quoi en ajouter. */
    public function index(Request $request): View
    {
        $ref = (string) $request->query('ref', '');
        $article = $this->article($ref);
        abort_unless($article, 404);

        return view('tagtoa::catalog.codes', [
            'ref'      => $ref,
            'article'  => $article,
            'codes'    => $this->codes->forArticle(Tenant::id(), $ref),
            'isMenu'   => $article instanceof Item,
        ]);
    }

    /**
     * Attribue un code scanné ou tapé.
     *
     * Un code déjà pris par un AUTRE article n'est pas réaffecté en silence :
     * deux articles qui partagent un code rendraient le scan aléatoire, et
     * c'est au marchand de dire lequel le garde.
     */
    public function attach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ref'   => ['required', 'string', 'max:32'],
            'code'  => ['required', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        $article = $this->article($data['ref']);
        abort_unless($article, 404);

        $code = Barcode::normalize($data['code']);

        if (! Barcode::isAcceptable($code)) {
            // Chiffre de contrôle faux : le code vient d'une lecture erronée ou
            // d'une saisie à la main. L'accepter créerait un article que
            // personne ne retrouverait jamais en scannant.
            return back()->withErrors(['code' => __('Ce code est incomplet ou mal lu. Rescannez-le, ou vérifiez les chiffres.')]);
        }

        $attribue = $this->codes->attach(Tenant::id(), $data['ref'], $code, ['label' => $data['label'] ?? null]);

        if (! $attribue) {
            return back()->withErrors(['code' => __('Ce code est déjà utilisé par un autre article de votre commerce. Retirez-le de là-bas d\'abord.')]);
        }

        app(AuditService::class)->log('catalog.code_attached', null, $article->name.' — '.$code);

        return back()->with('success', __('Code :code ajouté à :article.', [
            'code' => $code, 'article' => $article->name,
        ]));
    }

    /**
     * Fabrique une étiquette TAGTOA pour un article sans code imprimé.
     *
     * C'est ce qui rend le scanner utile au commerce de quartier : le pâté et
     * le fresco n'ont pas de code-barres, et n'en auront jamais.
     */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:32']]);

        $article = $this->article($data['ref']);
        abort_unless($article, 404);

        $code = $this->codes->generate(Tenant::id(), $data['ref']);

        if (! $code) {
            return back()->withErrors(['code' => __('Impossible de fabriquer une étiquette pour cet article.')]);
        }

        app(AuditService::class)->log('catalog.code_generated', null, $article->name.' — '.$code->code);

        return back()->with('success', __('Étiquette :code créée. Imprimez-la et collez-la sur l\'article.', [
            'code' => $code->code,
        ]));
    }

    /** Retire un code. Le marchand reste maître de ses étiquettes. */
    public function detach(Request $request, int $codeId): RedirectResponse
    {
        $supprime = $this->codes->detach(Tenant::id(), $codeId);
        abort_unless($supprime, 404);

        return back()->with('success', __('Code retiré.'));
    }

    /**
     * L'article désigné par « pos:7 » ou « menu:7 », DANS ce commerce.
     *
     * Jamais un find() nu : un identifiant deviné donnerait l'article du
     * voisin, et on lui attribuerait un code chez lui.
     */
    private function article(string $ref)
    {
        return CatalogRef::isValid($ref)
            ? $this->catalog->resolve(Tenant::id(), $ref)
            : null;
    }
}
