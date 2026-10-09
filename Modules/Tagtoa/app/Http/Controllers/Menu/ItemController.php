<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Catalog\Pricing;
use Modules\Tagtoa\App\Support\Menu\BusinessProfile;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA MENU — ajouter ou modifier UN produit, dans un écran à part.
 *
 * Signalé : « Ajouter un produit » ouvrait le même formulaire que créer ou
 * modifier l'établissement — nom, logo, adresse, WhatsApp, catégories, tout
 * mélangé. Cet écran ne montre QUE ce qui concerne le produit : catégorie,
 * nom, prix, photo, disponibilité, et les champs propres au métier.
 *
 * AUCUNE logique n'est dupliquée : l'enregistrement construit le même
 * tableau imbriqué `cats[][items][]` que l'assistant complet envoie déjà, et
 * le confie à DashboardController::update() — le même code testé, la même
 * validation, le même ledger de stock, la même garde « enregistrer ne
 * supprime jamais ». Seul l'ÉCRAN change ; la base n'est jamais touchée par
 * deux chemins différents.
 *
 * Les champs de CET écran portent tous le préfixe `item_` — jamais `name`
 * ou `description` nus : ce sont aussi des champs du MENU lui-même
 * (validateMenu()). Sans ce préfixe, enregistrer un produit écraserait le
 * nom ou la description de l'établissement avec ceux du produit.
 */
class ItemController extends Controller
{
    /**
     * « Produits », sans numéro de menu — même raccourci que Commandes/
     * Cuisine/Livraison/Tables (DashboardController::currentOrders() et
     * consorts) : un commerce n'a qu'UN menu, la sidebar peut donc lier
     * directement sans connaître son id.
     */
    public function currentIndex(): View|RedirectResponse
    {
        $menu = Menu::where('tenant_id', Tenant::id())->oldest()->first();
        if (! $menu) {
            return redirect()->route('tagtoa.menu.dashboard.wizard')
                ->with('info', __('Créez d\'abord votre menu.'));
        }

        return $this->index($menu->id);
    }

    public function index(int $menuId): View
    {
        $menu = $this->own($menuId, ['categories.items']);

        return view('tagtoa::menu.items.index', ['menu' => $menu]);
    }

    public function create(int $menuId): View
    {
        $menu = $this->own($menuId, ['categories']);

        return view('tagtoa::menu.items.form', $this->formData($menu));
    }

    public function edit(int $menuId, int $itemId): View
    {
        $menu = $this->own($menuId, ['categories']);
        $item = Item::where('menu_id', $menu->id)->whereKey($itemId)->firstOrFail();

        return view('tagtoa::menu.items.form', $this->formData($menu, $item));
    }

    public function store(Request $request, int $menuId): RedirectResponse
    {
        return $this->save($request, $menuId);
    }

    public function update(Request $request, int $menuId, int $itemId): RedirectResponse
    {
        return $this->save($request, $menuId, $itemId);
    }

    /**
     * Construit le même tableau `cats[0][items][0]` que le gros formulaire,
     * à partir des champs `item_*` de cet écran, et le confie à la route
     * déjà testée. Le nom de l'établissement et les autres catégories/
     * articles ne sont JAMAIS envoyés : la garde « enregistrer ne supprime
     * rien » de syncContent() les laisse intacts, absents de cet envoi.
     */
    private function save(Request $request, int $menuId, ?int $itemId = null): RedirectResponse
    {
        $menu = $this->own($menuId);

        $request->validate([
            'category_id'          => ['nullable', 'integer'],
            'new_category_name'    => ['nullable', 'string', 'max:120'],
            'item_name'            => ['required', 'string', 'max:160'],
            'item_description'     => ['nullable', 'string', 'max:600'],
            'item_price'           => ['required', 'numeric', 'min:0', 'max:99999999'],
            'item_cost_price'      => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'item_stock'           => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'item_low_stock_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'item_sku'             => ['nullable', 'string', 'max:60'],
            'item_image'           => ['nullable', 'image', 'max:2048'],
        ]);

        $category = null;
        if ($request->filled('category_id')) {
            $category = $menu->categories()->whereKey($request->input('category_id'))->first();
        }
        $categoryName = $category->name ?? trim((string) $request->input('new_category_name'));
        if ($categoryName === '') {
            return back()->withInput()->withErrors([
                'new_category_name' => __('Choisissez une catégorie, ou tapez-en une nouvelle.'),
            ]);
        }

        $item = $itemId ? Item::where('menu_id', $menu->id)->whereKey($itemId)->first() : null;
        abort_if($itemId && ! $item, 404);

        // Repère pour retrouver l'article créé : jamais par son nom (deux
        // plats peuvent s'appeler pareil), toujours par l'identifiant le
        // plus haut du menu avant l'écriture.
        $dernierIdAvant = (int) $menu->items()->max('id');

        $itemPayload = [
            'id'                  => $item?->id,
            'name'                => $request->input('item_name'),
            'description'         => $request->input('item_description'),
            'price'               => $request->input('item_price'),
            'cost_price'          => $request->input('item_cost_price'),
            'stock'               => $request->input('item_stock'),
            'low_stock_threshold' => $request->input('item_low_stock_threshold'),
            'sku'                 => $request->input('item_sku'),
            'supplier_id'         => $request->input('item_supplier_id'),
            'unit'                => $request->input('item_unit'),
            'badge'               => $request->input('item_badge'),
            'is_available'        => $request->boolean('item_is_available', true) ? '1' : '0',
            'is_featured'         => $request->boolean('item_is_featured') ? '1' : '0',
            'remove_image'        => $request->boolean('item_remove_image') ? '1' : '0',
            'specs'               => $request->input('specs', []),
            'translations_sent'   => '0',
            'options_sent'        => '0',
        ];

        // Mêmes clés que le gros formulaire (cats[0][items][0][…]) : le même
        // code déjà testé (DashboardController::update → syncContent) écrit
        // en base, sans jamais voir ni toucher le nom de l'établissement ni
        // les autres catégories/articles.
        $request->merge([
            'name'     => $menu->name,
            'form_end' => '1',
            'cats'     => [
                0 => [
                    'id'    => $category?->id,
                    'name'  => $categoryName,
                    'items' => [0 => $itemPayload],
                ],
            ],
        ]);
        if ($request->hasFile('item_image')) {
            $request->files->set('cats', [0 => ['items' => [0 => ['image' => $request->file('item_image')]]]]);
            // Request::allFiles() mémorise son résultat au premier appel — la
            // validation ci-dessus (règle `image` sur item_image) l'a déjà
            // invoqué, AVANT que `cats` n'existe dans le sac de fichiers.
            // Sans cette purge, syncContent() ne retrouverait jamais le
            // fichier à son nouveau chemin imbriqué.
            $this->oublierLeCacheDesFichiers($request);
        }

        app(DashboardController::class)->update($request, $menu->id);

        $nouveauId = $item?->id ?? $menu->items()->where('id', '>', $dernierIdAvant)->value('id');

        return redirect()->route('tagtoa.menu.dashboard.items.index', $menu->id)
            ->with('success', __(':nom enregistré.', ['nom' => $itemPayload['name']]))
            ->with('highlight_item', $nouveauId);
    }

    private function formData(Menu $menu, ?Item $item = null): array
    {
        return [
            'menu'      => $menu,
            'item'      => $item,
            'fields'    => BusinessProfile::fields($menu->type),
            'units'     => Pricing::UNITS,
            'suppliers' => \Modules\Tagtoa\App\Models\Inventory\Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function own(int $id, array $with = []): Menu
    {
        return Menu::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    /**
     * Force Request::allFiles() à se recalculer au prochain appel.
     *
     * Illuminate\Http\Request mémorise le résultat de allFiles() dans la
     * propriété privée `convertedFiles` dès son premier appel — ici,
     * déclenché par la règle de validation `image`. Sans cette purge, tout
     * code qui lit ensuite $request->file('cats.0...') (syncContent())
     * verrait l'ancien sac de fichiers, d'avant que `cats` n'y soit posé.
     */
    private function oublierLeCacheDesFichiers(Request $request): void
    {
        $prop = new \ReflectionProperty($request, 'convertedFiles');
        $prop->setAccessible(true);
        $prop->setValue($request, null);
    }
}
