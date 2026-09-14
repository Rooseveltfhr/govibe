<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Category;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — les rayons du commerce.
 *
 * Quarante articles donnaient quarante boutons d'affilée : le caissier
 * cherchait à l'œil au moment précis où il a le moins de temps. Les rayons
 * existaient depuis le début côté menu digital ; la caisse n'en avait pas,
 * alors qu'ils décrivent le même commerce.
 *
 * Le rayon appartient au COMMERCE : deux caisses vendent les mêmes rayons.
 * L'isolation est automatique (BelongsToTenant), mais toute écriture repasse
 * par `own()` — un identifiant vient du navigateur, jamais de la confiance.
 */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('tagtoa::pos.categories', [
            'categories' => Category::orderBy('sort')->orderBy('id')->withCount('products')->get(),
            // Les articles sans rayon : c'est la seule chose qu'un marchand
            // veut voir en arrivant ici, et le motif pour lequel il est venu.
            'sansRayon'  => Product::whereNull('category_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            // Une classe Font Awesome, jamais un emoji ni du texte libre : la
            // valeur part dans un attribut `class`, et une chaîne libre y
            // ferait entrer ce qu'on veut.
            'icon'  => ['nullable', 'string', 'max:40', 'regex:/^fa-[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Category::create([
            'tenant_id' => Tenant::id(),
            'name'      => $data['name'],
            'icon'      => $data['icon'] ?? null,
            'color'     => $data['color'] ?? null,
            // En dernier : un rayon neuf ne doit pas se glisser devant ceux que
            // le caissier connaît déjà par leur place.
            'sort'      => (int) Category::max('sort') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', __('Rayon « :nom » créé.', ['nom' => $data['name']]));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:80'],
            'icon'      => ['nullable', 'string', 'max:40', 'regex:/^fa-[a-z0-9-]+$/'],
            'color'     => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort'      => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $c = $this->own($id);

        $c->update([
            'name'      => $data['name'],
            'icon'      => $data['icon'] ?? null,
            'color'     => $data['color'] ?? null,
            'sort'      => $data['sort'] ?? $c->sort,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', __('Rayon mis à jour.'));
    }

    /**
     * Supprime un rayon — jamais ses articles.
     *
     * C'est LA règle de cet écran. Supprimer un rayon avec ses produits
     * effacerait un catalogue entier d'un clic, et le stock avec. Les articles
     * redeviennent simplement « sans rayon » et restent vendables.
     */
    public function destroy(int $id): RedirectResponse
    {
        $c = $this->own($id);
        $nom = $c->name;

        // Détachement EXPLICITE avant la suppression : la colonne n'a pas de
        // contrainte étrangère (pour que rien ne puisse bloquer une vente), donc
        // personne ne le fera à notre place — les articles garderaient
        // l'identifiant d'un rayon disparu, et disparaîtraient de tous les
        // filtres sans jamais réapparaître.
        Product::where('category_id', $c->id)->update(['category_id' => null]);

        $c->delete();

        return back()->with('success', __('Rayon « :nom » supprimé. Ses articles restent en vente.', ['nom' => $nom]));
    }

    /** Un rayon de CE commerce, ou 404. Jamais un find() nu. */
    private function own(int $id): Category
    {
        return Category::whereKey($id)->firstOrFail();
    }
}
