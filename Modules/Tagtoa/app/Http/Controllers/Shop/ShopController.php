<?php

namespace Modules\Tagtoa\App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Shop\ShopItem;
use Modules\Tagtoa\App\Models\Shop\ShopOrder;
use Modules\Tagtoa\App\Services\Shop\ShopService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * BOUTIQUE TAGTOA — le marchand commande son matériel.
 *
 * Le Smart Stand existait de bout en bout SAUF le début : comment un restaurant
 * obtient-il ses quarante stands ? Par WhatsApp, au jugé, sans trace — et une
 * commande passée par message se perd, se discute, et n'existe dans aucun
 * chiffre.
 *
 * Le formulaire n'envoie que des identifiants d'article et des quantités. Le
 * prix est relu au catalogue, comme partout ailleurs : accepter un total venu
 * de la page, ce serait laisser commander à son propre tarif.
 */
class ShopController extends Controller
{
    public function __construct(protected ShopService $shop)
    {
    }

    public function index(Request $request): View
    {
        // Le panier vit en session, pas en base : un marchand qui hésite entre
        // vingt et quarante stands ne doit pas laisser des lignes mortes
        // derrière lui à chaque changement d'avis.
        $panier = $this->panier($request);

        return view('tagtoa::shop.index', [
            'items'   => ShopItem::shown()->get(),
            'panier'  => $panier,
            'chiffre' => $this->shop->chiffrer($panier),
            'commerce' => Business::find(Tenant::id()),
        ]);
    }

    /** Met le panier à jour. Un seul chemin, pour ajouter comme pour retirer. */
    public function updateCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qty'    => ['nullable', 'array'],
            'qty.*'  => ['nullable', 'integer', 'min:0', 'max:100000'],
            'add'    => ['nullable', 'integer'],
        ]);

        $panier = $this->panier($request);

        // « Ajouter » depuis une carte : une quantité de départ qui respecte
        // déjà le minimum de l'article, pour ne pas afficher « 1 » sur un
        // article qui se vend par dix.
        if (! empty($data['add'])) {
            $article = ShopItem::shown()->whereKey((int) $data['add'])->first();
            if ($article) {
                $actuel = (int) ($panier[$article->id] ?? 0);
                $panier[$article->id] = $article->normalizeQty($actuel + max(1, (int) $article->step_qty));
            }
        }

        foreach ($data['qty'] ?? [] as $id => $q) {
            $q = (int) $q;
            if ($q <= 0) {
                unset($panier[(int) $id]);
                continue;
            }
            $panier[(int) $id] = $q;
        }

        $request->session()->put('tagtoa_shop_cart', $panier);

        return back();
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $panier = $this->panier($request);
        if ($panier === []) {
            return redirect()->route('tagtoa.shop.index');
        }

        $commerce = Business::find(Tenant::id());

        return view('tagtoa::shop.checkout', [
            'chiffre'  => $this->shop->chiffrer($panier),
            'commerce' => $commerce,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_name'    => ['required', 'string', 'max:120'],
            'contact_phone'   => ['required', 'string', 'max:40'],
            'address'         => ['required', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:80'],
            'note'            => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $resultat = $this->shop->commander($this->panier($request), Tenant::id(), $data);

        if (in_array($resultat['result'], [ShopService::OK, ShopService::DEJA_FAIT], true)) {
            // Le panier se vide SEULEMENT une fois la commande en base : vidé
            // avant, un échec d'écriture laisserait le marchand devant une
            // boutique vide sans savoir s'il a commandé.
            $request->session()->forget('tagtoa_shop_cart');

            return redirect()->route('tagtoa.shop.orders')->with('success',
                $resultat['result'] === ShopService::OK
                    ? __('Commande :ref envoyée. TAGTOA vous confirme le transport et le délai.',
                        ['ref' => $this->court($resultat['order'])])
                    : __('Cette commande était déjà enregistrée.'));
        }

        return back()->withInput()->with('error', match ($resultat['result']) {
            ShopService::VIDE => __('Votre panier est vide.'),
            default           => __('Ces articles ne sont plus disponibles. Reprenez votre panier.'),
        });
    }

    /** Les commandes du commerce. Isolation automatique (BelongsToTenant). */
    public function orders(): View
    {
        return view('tagtoa::shop.orders', [
            'orders' => ShopOrder::with('items')->orderByDesc('placed_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    /* ---------------- interne ---------------- */

    /** @return array<int,int> */
    private function panier(Request $request): array
    {
        $brut = $request->session()->get('tagtoa_shop_cart', []);

        if (! is_array($brut)) {
            return [];
        }

        $out = [];
        foreach ($brut as $id => $q) {
            if ((int) $id > 0 && (int) $q > 0) {
                $out[(int) $id] = (int) $q;
            }
        }

        return $out;
    }

    /** Les huit premiers caractères de la référence : lisibles à l'oral. */
    private function court(?ShopOrder $o): string
    {
        return $o ? strtoupper(substr($o->reference, 0, 8)) : '';
    }
}
