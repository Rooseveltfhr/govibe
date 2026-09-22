<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Support\Locale;
use Modules\Tagtoa\App\Support\Menu\BusinessHours;
use Modules\Tagtoa\App\Support\Menu\BusinessProfile;
use Modules\Tagtoa\App\Support\Menu\Translatable;
use Modules\Tagtoa\App\Support\Catalog\Pricing;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA MENU — dashboard propriétaire (CRUD menu + catégories + items).
 */
class DashboardController extends Controller
{
    use EnforcesPlan;

    public function index(): View
    {
        $menus = Menu::where('tenant_id', Tenant::id())
            // Une seule règle « stock faible » dans tout TAGTOA : celle de
            // l'article quand le commerce l'a réglée, le plancher commun sinon.
            ->withCount(['items', 'categories', 'items as low_stock_count' => fn ($q) => $q->lowStock()])
            ->latest()->paginate(12);

        return view('tagtoa::menu.index', compact('menus'));
    }

    public function create(): View
    {
        return view('tagtoa::menu.form', $this->creationViewData());
    }

    /**
     * Même création, présentée en assistant à sept étapes au lieu d'un long
     * formulaire — même formulaire, mêmes champs, seulement redécoupé à
     * l'écran (voir menu/_form-body.blade.php, partagé par les deux vues).
     */
    public function wizard(): View
    {
        return view('tagtoa::menu.wizard', $this->creationViewData());
    }

    /**
     * Données communes aux deux écrans de création (formulaire classique et
     * assistant). Le commerce (l'établissement) porte déjà nom, logo, adresse,
     * téléphone, type et devise — les redemander à la création du menu fait
     * taper deux fois la même chose, et les deux copies finissent par
     * diverger. On les reprend comme PRÉ-REMPLISSAGE seulement : le marchand
     * garde la main pour les changer si ce menu-là diffère (un hôtel dont le
     * restaurant a son propre numéro, par exemple) — aucun champ n'est retiré
     * du formulaire.
     */
    private function creationViewData(): array
    {
        $business = Business::find(Tenant::id());

        return [
            'menu' => new Menu([
                'theme'        => 'light',
                'accent_color' => '#2cb809',
                'currency'     => $business->currency ?? Locale::currencyFor(),
                'type'         => $business->type ?? null,
                'logo_path'    => $business->logo_path ?? null,
                'address'      => $business->address ?? null,
                'phone'        => $business->phone ?? null,
            ]),
            'vcards'    => $this->vcards(),
            'payPages'  => $this->payPages(),
            'suppliers' => $this->fournisseurs(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        
        if ($r = $this->planGuard('menu')) {
            return $r;
        }
$data = $this->validateMenu($request);
        $menu = new Menu($data);
        $menu->tenant_id = Tenant::id();
        $menu->alias = $data['alias'] ?: Menu::generateAlias($data['name'] ?? 'menu');
        $this->syncTranslations($menu, $request);
        $this->handleUploads($menu, $request);
        // Aucun logo envoyé pour CE menu : celui du commerce sert de défaut,
        // au lieu d'obliger à le renvoyer une deuxième fois (un fichier ne se
        // pré-remplit pas dans un <input type="file"> — la reprise se fait
        // ici plutôt que côté formulaire).
        if (! $menu->logo_path) {
            $menu->logo_path = Business::find(Tenant::id())?->logo_path;
        }
        $menu->save();
        $this->syncContent($menu, $request);
        $this->syncDeliveryZones($menu, $request);

        return redirect()->route('tagtoa.menu.dashboard.edit', $menu->id)
            ->with('success', __('Menu créé. Ajoutez vos catégories et produits.'));
    }

    public function edit(int $id): View
    {
        $menu = $this->own($id, ['categories.items.options.choices', 'deliveryZones']);

        return view('tagtoa::menu.form', [
            'menu'     => $menu,
            'vcards'    => $this->vcards(),
            'payPages'  => $this->payPages(),
            'suppliers' => $this->fournisseurs(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $menu = $this->own($id);
        $data = $this->validateMenu($request, $menu->id);
        // Champ absent de l'envoi : on garde l'alias existant plutôt que de
        // planter. Une clé manquante ne doit jamais rendre une page blanche.
        $data['alias'] = ($data['alias'] ?? null) ?: $menu->alias;
        $menu->fill($data);
        $this->syncTranslations($menu, $request);
        $this->handleUploads($menu, $request);
        $menu->save();
        $this->syncContent($menu, $request);
        $this->syncDeliveryZones($menu, $request);

        return back()->with('success', __('Menu mis à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->own($id)->delete();

        return redirect()->route('tagtoa.menu.dashboard.index')->with('success', __('Menu supprimé.'));
    }

    /* ---------- commandes ---------- */

    public function orders(int $id): View
    {
        $menu = $this->own($id);
        $orders = $menu->orders()->with('items')->paginate(20);
        $pending = $menu->orders()->where('status', 'pending')->count();

        return view('tagtoa::menu.orders', compact('menu', 'orders', 'pending'));
    }

    /* =====================================================================
       TABLES — vérifiées par QR/NFC, jamais un numéro tapé par le client.
       ===================================================================== */

    public function tables(int $id): View
    {
        $menu = $this->own($id);

        return view('tagtoa::menu.tables', ['menu' => $menu, 'tables' => $menu->tables]);
    }

    public function storeTable(Request $request, int $id): RedirectResponse
    {
        $menu = $this->own($id);
        $data = $request->validate(['label' => ['required', 'string', 'max:40']]);

        $menu->tables()->create([
            'tenant_id' => $menu->tenant_id,
            'label'     => $data['label'],
            'code'      => \Modules\Tagtoa\App\Models\Menu\Table::generateCode(),
            'is_active' => true,
        ]);

        return back()->with('success', __('Table ajoutée. Imprimez son QR et posez-le dessus.'));
    }

    public function destroyTable(int $id, int $tableId): RedirectResponse
    {
        $menu = $this->own($id);
        $menu->tables()->whereKey($tableId)->firstOrFail()->delete();

        return back()->with('success', __('Table supprimée.'));
    }

    /** Affiche imprimable — réutilise le même gabarit que les autres QR TAGTOA. */
    public function tablePoster(int $id, int $tableId): View
    {
        $menu = $this->own($id);
        $table = $menu->tables()->whereKey($tableId)->firstOrFail();

        return view('tagtoa::qr.poster', [
            'name'  => $menu->name.' — '.$table->label,
            'label' => __('Table'),
            'url'   => url('/menu/'.$menu->alias).'?t='.$table->code,
        ]);
    }

    /**
     * Écran cuisine : lecture seule, oldest-first — la commande qui attend
     * depuis le plus longtemps est celle qu'il faut sortir en premier.
     */
    public function kitchen(int $id): View
    {
        $menu = $this->own($id);

        return view('tagtoa::menu.kitchen', ['menu' => $menu]);
    }

    /** Le même écran, en JSON : ce que la page interroge toutes les quelques secondes. */
    public function kitchenFeed(int $id): \Illuminate\Http\JsonResponse
    {
        $menu = $this->own($id);
        $orders = $menu->orders()
            ->whereIn('status', Order::KITCHEN_STATUSES)
            ->with('items')->oldest('placed_at')->get();

        $staff = app(\Modules\Tagtoa\App\Services\Staff\StaffService::class)->forMenu($menu);

        return response()->json([
            // null = le patron opère directement (toujours autorisé) — voir
            // kitchenAdvance(). Un employé identifié doit avoir la case cochée.
            'staff'       => $staff ? ['name' => $staff->name, 'initials' => $staff->initials] : null,
            'can_advance' => ! $staff || $staff->canRunKitchen(),
            'orders' => $orders->map(fn (Order $o) => [
                'id'          => $o->id,
                'reference'   => $o->reference,
                'status'      => $o->status,
                'status_label' => __($o->status_meta['label']),
                'next_status' => self::KITCHEN_NEXT[$o->status] ?? null,
                'order_type'  => $o->order_type,
                'order_type_label' => __($o->order_type_label),
                'table_label' => $o->table_label,
                'note'        => $o->note,
                'placed_at'   => optional($o->placed_at)->toIso8601String(),
                'items'       => $o->items->map(fn ($it) => [
                    'name' => $it->name, 'qty' => (int) $it->qty,
                    'options' => collect($it->selected_options ?: [])->pluck('label')->filter()->values(),
                ]),
            ]),
        ]);
    }

    /**
     * Étape suivante du cycle cuisine (pending/confirmed → preparing → ready),
     * jamais au-delà : servir + encaisser une commande « Prête » se fait sur
     * l'écran CAISSE (counter*, plus bas), pas ici — annuler une commande
     * reste sur l'écran « Commandes », qui seul connaît le reste du cycle.
     */
    private const KITCHEN_NEXT = [
        'pending'   => 'preparing',
        'confirmed' => 'preparing',
        'preparing' => 'ready',
    ];

    /**
     * Fait avancer une commande d'une étape depuis l'écran cuisine.
     *
     * AUCUN employé identifié ne veut pas dire « personne » : c'est le patron
     * qui opère l'écran directement (même convention que GuardsStaffAbility
     * côté POS) — la vraie frontière de sécurité reste le garde
     * `role:admin|super_admin` sur ces routes. La restriction par rôle « cuisine »
     * ne s'applique qu'une fois un employé identifié via son code.
     */
    public function kitchenAdvance(int $id, int $orderId): RedirectResponse
    {
        $menu = $this->own($id);
        $order = $menu->orders()->whereKey($orderId)->firstOrFail();

        $staff = app(\Modules\Tagtoa\App\Services\Staff\StaffService::class)->forMenu($menu);
        abort_if($staff && ! $staff->canRunKitchen(), 403, __('Vous n\'avez pas le droit de faire cela.'));

        $suivant = self::KITCHEN_NEXT[$order->status] ?? null;
        if ($suivant) {
            $order->update(['status' => $suivant]);
            app(\Modules\Tagtoa\App\Services\Order\OrderSpine::class)->touch('menu_order', $order->id, $suivant);
            $this->notifyCustomerOfStatus($order);
        }

        return back();
    }

    /** PIN de l'employé identifié sur CET écran cuisine (espace de session distinct de la caisse). */
    public function kitchenStaffLogin(Request $request, int $id): RedirectResponse
    {
        $menu = $this->own($id);
        $data = $request->validate(['pin' => ['required', 'string']]);

        $staff = app(\Modules\Tagtoa\App\Services\Staff\StaffService::class)
            ->authenticate($menu->tenant_id, $data['pin']);

        if (! $staff) {
            return back()->withErrors(['pin' => __('Code incorrect.')]);
        }

        session(['tagtoa_menu_staff.'.$menu->id => $staff->id]);

        return back()->with('success', __('Bonjour :nom.', ['nom' => $staff->name]));
    }

    public function kitchenStaffLogout(int $id): RedirectResponse
    {
        session()->forget('tagtoa_menu_staff.'.$id);

        return back();
    }

    /* =====================================================================
       ÉCRAN CAISSE — canal MENU. Complète le cycle ouvert par la cuisine :
       la cuisine amène une commande à « Prête » (kitchen*), le comptoir la
       sert et encaisse. Deux écrans, deux gestes, jamais confondus — un
       cuisinier ne doit pas pouvoir marquer une commande payée, et
       inversement une personne au comptoir n'a pas à voir la file de
       préparation.
       ===================================================================== */

    public function counter(int $id): View
    {
        $menu = $this->own($id);

        return view('tagtoa::menu.counter', ['menu' => $menu]);
    }

    /** Le même écran, en JSON — polling, comme la cuisine. */
    public function counterFeed(int $id): \Illuminate\Http\JsonResponse
    {
        $menu = $this->own($id);
        $orders = $menu->orders()->where('status', 'ready')
            ->with('items')->oldest('placed_at')->get();

        $staff = app(\Modules\Tagtoa\App\Services\Staff\StaffService::class)->forMenu($menu);

        return response()->json([
            'staff'       => $staff ? ['name' => $staff->name, 'initials' => $staff->initials] : null,
            // « Encaisser » est un droit de caisse ordinaire (StaffAccess::ABILITIES),
            // pas la case « cuisine » : servir et prendre le paiement, c'est
            // exactement ce que 'sell' signifie déjà partout ailleurs dans TAGTOA.
            'can_complete' => ! $staff || $staff->can('sell'),
            'orders' => $orders->map(fn (Order $o) => [
                'id'          => $o->id,
                'reference'   => $o->reference,
                'order_type'  => $o->order_type,
                'order_type_label' => __($o->order_type_label),
                'table_label' => $o->table_label,
                'total'       => (string) $o->total,
                'currency'    => $o->currency,
                'is_paid'     => $o->isPaid(),
                'placed_at'   => optional($o->placed_at)->toIso8601String(),
                'items'       => $o->items->map(fn ($it) => [
                    'name' => $it->name, 'qty' => (int) $it->qty,
                ]),
            ]),
        ]);
    }

    /**
     * Sert ET encaisse une commande « Prête » — jamais séparément depuis cet
     * écran : marquer servi sans encaisser laisserait une addition non
     * réglée disparaître de la file, invisible jusqu'au rapport du soir.
     *
     * Réutilise MenuOrderService::markPaid() (revenu + points fidélité) plutôt
     * que de réécrire cette logique : un seul endroit sait ce qu'encaisser une
     * commande MENU veut dire.
     */
    public function counterComplete(int $id, int $orderId): RedirectResponse
    {
        $menu = $this->own($id);
        $order = $menu->orders()->whereKey($orderId)->firstOrFail();

        $staff = app(\Modules\Tagtoa\App\Services\Staff\StaffService::class)->forMenu($menu);
        abort_if($staff && ! $staff->can('sell'), 403, __('Vous n\'avez pas le droit de faire cela.'));

        if ($order->status === 'ready') {
            $order->update(['status' => 'completed']);
            // AVANT markPaid() : celui-ci fait sa propre écriture
            // (payment_status), qui effacerait wasChanged('status') d'ici —
            // la garde anti-doublon ne verrait alors plus jamais ce
            // changement-ci.
            $this->notifyCustomerOfStatus($order);
            app(MenuOrderService::class)->markPaid($order);
            app(\Modules\Tagtoa\App\Services\Order\OrderSpine::class)->touch('menu_order', $order->id, 'completed');
        }

        return back();
    }

    public function setStatus(Request $request, int $orderId): RedirectResponse
    {
        $order = $this->ownOrder($orderId);
        $data = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);
        $order->update(['status' => $data['status']]);

        // Le module reste maître de l'état réel ; la colonne vertébrale suit,
        // pour que le rapport commun ne montre pas une commande déjà livrée
        // comme encore à préparer.
        app(\Modules\Tagtoa\App\Services\Order\OrderSpine::class)
            ->touch('menu_order', $order->id, $data['status']);
        $this->notifyCustomerOfStatus($order);

        return back()->with('success', __('Commande mise à jour.'));
    }

    /**
     * Avertit le client par WhatsApp qu'une commande LIVRAISON vient de
     * changer d'étape (confirmée, en route, livrée) — jamais si le statut
     * n'a en fait pas bougé (un merchant qui re-choisit le même statut dans
     * le menu déroulant ne doit pas renvoyer le même message une deuxième
     * fois). Voir NotificationService::notifyOrderStatus() pour le filtre
     * sur order_type et les statuts qui comptent vraiment pour le client.
     */
    private function notifyCustomerOfStatus(Order $order): void
    {
        if ($order->wasChanged('status')) {
            app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class)->notifyOrderStatus($order);
        }
    }

    public function markPaid(int $orderId): RedirectResponse
    {
        $order = $this->ownOrder($orderId);
        app(MenuOrderService::class)->markPaid($order);
        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)->log('order.paid', $order, $order->reference);

        return back()->with('success', __('Paiement effectué.'));
    }

    protected function ownOrder(int $id): Order
    {
        return Order::whereHas('menu', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
    }

    /* ---------- supprimer : un acte délibéré du patron ---------- */

    /**
     * Supprime UN article du menu.
     *
     * Séparé de l'enregistrement pour la même raison qu'au comptoir (0.1b) :
     * un envoi incomplet ne doit jamais valoir suppression. Depuis B-3 la
     * caisse vend ce catalogue, donc l'article emporterait son stock avec lui.
     *
     * Les ventes déjà encaissées gardent le nom et le prix figés sur leur
     * ligne : supprimer un plat ne réécrit aucun historique.
     */
    public function destroyItem(int $id, int $itemId): RedirectResponse
    {
        $menu = $this->own($id);

        $item = Item::where('menu_id', $menu->id)->whereKey($itemId)->first();
        abort_unless($item, 404);

        $nom = $item->name;
        $item->delete();

        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)
            ->log('menu.item_deleted', null, $nom);

        return back()->with('success', __('Article supprimé du menu.').' ('.$nom.')');
    }

    /**
     * Supprime UNE catégorie et les articles qu'elle contient.
     *
     * Volontairement explicite : c'est l'action la plus lourde de l'écran, elle
     * ne doit jamais arriver par accident. Le nombre d'articles emportés est
     * annoncé au retour pour que le patron voie ce qu'il vient de faire.
     */
    public function destroyCategory(int $id, int $categoryId): RedirectResponse
    {
        $menu = $this->own($id);

        $cat = $menu->categories()->whereKey($categoryId)->first();
        abort_unless($cat, 404);

        $nom = $cat->name;
        $combien = $cat->items()->count();

        DB::transaction(function () use ($cat) {
            $cat->items()->delete();
            $cat->delete();
        });

        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)
            ->log('menu.category_deleted', null, $nom.' ('.$combien.')');

        return back()->with('success', trans_choice(
            '{0}Catégorie supprimée.|{1}Catégorie supprimée avec 1 article.|[2,*]Catégorie supprimée avec :count articles.',
            $combien
        ));
    }

    /* ---------- helpers ---------- */

    protected function own(int $id, array $with = []): Menu
    {
        return Menu::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function handleUploads(Menu $menu, Request $request): void
    {
        if ($request->hasFile('logo')) {
            $menu->logo_path = $request->file('logo')->store('tagtoa/menu-logos', 'public');
        }
        if ($request->hasFile('cover')) {
            $menu->cover_path = $request->file('cover')->store('tagtoa/menu-covers', 'public');
        }
    }

    protected function validateMenu(Request $request, ?int $ignoreId = null): array
    {
        // Le contenu est contrôlé ICI, donc AVANT que le menu ne soit
        // enregistré : sinon un plat refusé laisserait l'en-tête déjà écrit et
        // le marchand verrait une erreur sur un menu à moitié modifié.
        $this->validateContent($request);

        $ownVcardIds = $this->vcards()->pluck('id')->all();
        $ownPayIds   = $this->payPages()->pluck('id')->all();

        $data = $request->validate([
            'vcard_id'         => ['nullable', 'integer', Rule::in($ownVcardIds)],
            'name'             => ['required', 'string', 'max:160'],
            'alias'            => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_menus,alias'.($ignoreId ? ','.$ignoreId : '')],
            'type'             => ['nullable', Rule::in(array_keys(Menu::TYPES))],
            'tagline'          => ['nullable', 'string', 'max:160'],
            'description'      => ['nullable', 'string', 'max:600'],
            'currency'         => ['nullable', Rule::in(array_keys((array) config('tagtoa.currencies', [])))],
            'whatsapp'         => ['nullable', 'string', 'max:40'],
            'phone'            => ['nullable', 'string', 'max:40'],
            'address'          => ['nullable', 'string', 'max:200'],
            'pay_page_id'      => ['nullable', 'integer', Rule::in($ownPayIds)],
            'accent_color'     => ['nullable', 'string', 'max:16'],
            'theme'            => ['nullable', Rule::in(Menu::THEMES)],
            'show_prices'      => ['nullable', 'boolean'],
            'ordering_enabled' => ['nullable', 'boolean'],
            'is_active'        => ['nullable', 'boolean'],
            'delivery_fee'     => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'logo'             => ['nullable', 'image', 'max:2048'],
            'cover'            => ['nullable', 'image', 'max:4096'],
            'show_hours'       => ['nullable', 'boolean'],
            'timezone'         => ['nullable', 'string', 'max:64', Rule::in(\DateTimeZone::listIdentifiers())],
            // Structure libre ici : chaque jour est nettoyé par
            // BusinessHours::sanitize(), qui ignore silencieusement tout ce
            // qui n'est pas une heure valide plutôt que de rejeter l'envoi.
            'hours'            => ['nullable', 'array'],
        ]);

        $data['hours'] = BusinessHours::sanitize($data['hours'] ?? null);

        return $data;
    }

    /**
     * Catégories et articles du formulaire imbriqué.
     *
     * Ces champs n'étaient pas validés : un prix négatif, un stock aberrant ou
     * une unité inventée entraient tels quels en base et ressortaient au
     * moment d'encaisser au comptoir — la caisse vend le menu depuis B-3.
     */
    protected function validateContent(Request $request): void
    {
        $this->refuseUnEnvoiTronque($request);

        $request->validate([
            'cats'                               => ['array', 'max:200'],
            'cats.*.name'                        => ['nullable', 'string', 'max:120'],
            // Le formulaire ne propose plus ce champ (icône déduite du nom) ;
            // la règle reste pour tout appel direct à l'API qui en enverrait un.
            'cats.*.icon'                        => ['nullable', 'string', 'max:40', 'regex:/^fa-[a-z0-9-]+$/'],
            'cats.*.items'                       => ['array', 'max:500'],
            'cats.*.items.*.name'                => ['nullable', 'string', 'max:160'],
            'cats.*.items.*.price'               => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cats.*.items.*.cost_price'          => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'cats.*.items.*.stock'               => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'cats.*.items.*.low_stock_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'cats.*.items.*.unit'                => ['nullable', 'string', Rule::in(array_keys(Pricing::UNITS))],
            'cats.*.items.*.sku'                 => ['nullable', 'string', 'max:60'],
            'cats.*.items.*.supplier_id'         => ['nullable', 'integer'],
            'cats.*.items.*.description'         => ['nullable', 'string', 'max:600'],
            'cats.*.items.*.badge'               => ['nullable', 'string', 'max:60'],
            'cats.*.translations.*.name'                => ['nullable', 'string', 'max:120'],
            'cats.*.items.*.translations.*.name'        => ['nullable', 'string', 'max:160'],
            'cats.*.items.*.translations.*.description' => ['nullable', 'string', 'max:600'],
            'delivery_zones'             => ['nullable', 'array', 'max:50'],
            'delivery_zones.*.id'        => ['nullable', 'integer'],
            'delivery_zones.*.name'      => ['nullable', 'string', 'max:80'],
            'delivery_zones.*.fee'       => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);
    }

    /**
     * Synchronise les zones de livraison (nom + frais). Liste courte —
     * contrairement au catalogue (syncContent), un envoi qui ne renvoie plus
     * une zone la supprime : le marchand n'a aucun autre moyen de la retirer,
     * et une poignée de zones ne risque pas la troncature de max_input_vars.
     */
    protected function syncDeliveryZones(Menu $menu, Request $request): void
    {
        $rows = $request->input('delivery_zones', []);
        $keep = [];
        foreach ($rows as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $attrs = [
                'name'      => $row['name'],
                'fee'       => max(0, round((float) ($row['fee'] ?? 0), 2)),
                'sort'      => (int) $i,
                'is_active' => true,
            ];
            $zone = ! empty($row['id']) ? $menu->deliveryZones()->whereKey($row['id'])->first() : null;
            $zone ? $zone->update($attrs) : $zone = $menu->deliveryZones()->create($attrs);
            $keep[] = $zone->id;
        }
        $menu->deliveryZones()->whereNotIn('id', $keep ?: [0])->delete();
    }

    /**
     * Refuse un formulaire arrivé incomplet.
     *
     * PHP coupe $_POST au-delà de `max_input_vars` (1000 par défaut) SANS rien
     * dire. Une carte de soixante plats dépasse ce plafond : le marchand
     * cliquait « Enregistrer » et la fin de son menu n'arrivait jamais au
     * serveur. Depuis que l'enregistrement ne supprime plus rien, il ne perd
     * plus ses plats — mais ses dernières modifications seraient quand même
     * passées à la trappe en silence, ce qui est presque aussi grave.
     *
     * Le formulaire pose donc un jeton en TOUT DERNIER champ. S'il manque alors
     * que du contenu a été envoyé, c'est que la fin a été coupée : on refuse
     * l'écriture entière et on le dit, plutôt que d'enregistrer à moitié.
     */
    protected function refuseUnEnvoiTronque(Request $request): void
    {
        if (! $request->has('cats') || $request->boolean('form_end')) {
            return;
        }

        $limite = (int) ini_get('max_input_vars');

        throw \Illuminate\Validation\ValidationException::withMessages([
            'cats' => __("L'envoi est arrivé incomplet et n'a pas été enregistré. Votre menu est intact. Réduisez le nombre d'articles enregistrés en une fois (limite du serveur : :n champs), ou demandez à votre hébergeur d'augmenter max_input_vars.", ['n' => $limite ?: 1000]),
        ]);
    }

    /**
     * Enregistre le stock saisi au menu, via le journal.
     *
     * Champ absent de l'envoi : on n'y touche pas. Champ vidé : l'article
     * repasse en « non suivi », ce qui est une décision et non un mouvement —
     * il n'y a plus rien à compter.
     */
    private function noteLeStock(Item $item, array $ligne, bool $nouveau): void
    {
        if (! array_key_exists('stock', $ligne)) {
            return;
        }

        $valeur = $this->nombreOuNull($ligne['stock'], 0);

        if ($valeur === null) {
            if ($item->stock !== null) {
                $item->forceFill(['stock' => null])->save();
            }

            return;
        }

        app(\Modules\Tagtoa\App\Services\Inventory\StockLedger::class)->count($item, $valeur, [
            'reason' => $nouveau ? __('Stock initial') : __('Saisie au menu'),
        ]);
    }

    /** L'annuaire actif du commerce, pour la liste déroulante des articles. */
    private function fournisseurs()
    {
        return \Modules\Tagtoa\App\Models\Inventory\Supplier::where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);
    }

    /** Un fournisseur de CE commerce, sinon rien. */
    private function fournisseur(mixed $id): ?int
    {
        return $id ? \Modules\Tagtoa\App\Models\Inventory\Supplier::whereKey((int) $id)->value('id') : null;
    }

    /** Champ numérique laissé vide = « non renseigné », pas « zéro ». */
    private function nombreOuNull(mixed $valeur, ?float $minimum = null): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        $nombre = (float) $valeur;

        return $minimum === null ? $nombre : max($minimum, $nombre);
    }

    /**
     * Le slogan et la description de l'établissement, dans chaque langue.
     *
     * Même garde que pour un article : rien ne s'écrit sans le marqueur du
     * formulaire, pour qu'un envoi qui ne connaît pas ce panneau (vieux
     * gabarit en cache, appel API direct) ne remette jamais les traductions à
     * zéro.
     */
    protected function syncTranslations(Menu $menu, Request $request): void
    {
        if (! $request->boolean('translations_sent')) {
            return;
        }

        $menu->translations = Translatable::sanitize(
            $request->input('translations'), Locale::codes(), Menu::CHAMPS_TRADUISIBLES
        );
    }

    /**
     * Synchronise catégories + items depuis le formulaire imbriqué (cats[][items][]).
     * Important : on NE réindexe PAS les tableaux (pas d'array_values) — les clés
     * $ci/$ii doivent rester celles soumises par le navigateur pour que
     * $request->file("cats.$ci.items.$ii.image") retrouve le bon fichier.
     */
    protected function syncContent(Menu $menu, Request $request): void
    {
        $cats = $request->input('cats', []);
        $keepCats = [];

        DB::transaction(function () use ($menu, $cats, $request, &$keepCats) {
            foreach ($cats as $ci => $c) {
                if (empty($c['name'])) {
                    continue;
                }
                $catAttrs = [
                    'name'      => $c['name'],
                    'icon'      => $c['icon'] ?? null,
                    'sort'      => (int) $ci,
                    'is_active' => true,
                ];
                // Marqueur posé par le formulaire, comme `options_sent` pour
                // les options d'article : sans lui, on n'écrit RIEN — un envoi
                // partiel ou un vieux gabarit sans le panneau de traduction ne
                // doit jamais effacer une traduction déjà enregistrée.
                if (! empty($c['translations_sent'])) {
                    $catAttrs['translations'] = Translatable::sanitize(
                        $c['translations'] ?? null, Locale::codes(),
                        \Modules\Tagtoa\App\Models\Menu\Category::CHAMPS_TRADUISIBLES
                    );
                }
                $cat = ! empty($c['id']) ? $menu->categories()->whereKey($c['id'])->first() : null;
                $cat ? $cat->update($catAttrs) : $cat = $menu->categories()->create($catAttrs);
                $keepCats[] = $cat->id;

                $keepItems = [];
                foreach (($c['items'] ?? []) as $ii => $it) {
                    if (empty($it['name'])) {
                        continue;
                    }
                    $itemAttrs = [
                        'menu_id'      => $menu->id,
                        'name'         => $it['name'],
                        'description'  => $it['description'] ?? null,
                        'price'        => round((float) ($it['price'] ?? 0), 2),
                        'emoji'        => $it['emoji'] ?? null,
                        'badge'        => $it['badge'] ?? null,
                        // Champs propres au métier (chambre, boisson, plat…) :
                        // seuls ceux déclarés pour CE type entrent en base, chacun
                        // contraint à son domaine. Rien d'inconnu n'est écrit.
                        'specs'        => BusinessProfile::sanitize($menu->type, $it['specs'] ?? null),
                        'is_featured'  => ! empty($it['is_featured']),
                        'is_available' => ! isset($it['is_available']) ? true : (bool) $it['is_available'],
                        'sort'         => (int) $ii,

                        // Coût matière, unité, seuil, référence — même volet
                        // commercial que la caisse (un seul catalogue depuis B-3).
                        // Le coût reste null quand il n'est pas renseigné :
                        // « 0 » ferait croire que la marge est totale.
                        'cost_price'          => $this->nombreOuNull($it['cost_price'] ?? null, 0),
                        'unit'                => Pricing::unit($it['unit'] ?? null),
                        'low_stock_threshold' => $this->nombreOuNull($it['low_stock_threshold'] ?? null, 0),
                        'sku'                 => trim((string) ($it['sku'] ?? '')) ?: null,
                        // Cloisonné : un identifiant deviné ne doit pas
                        // rattacher le fournisseur du commerce d'à côté.
                        'supplier_id'         => $this->fournisseur($it['supplier_id'] ?? null),
                    ];
                    // Même garde qu'à la catégorie : jamais écrit sans le
                    // marqueur du formulaire.
                    if (! empty($it['translations_sent'])) {
                        $itemAttrs['translations'] = Translatable::sanitize(
                            $it['translations'] ?? null, Locale::codes(),
                            Item::CHAMPS_TRADUISIBLES
                        );
                    }
                    $item = ! empty($it['id']) ? $cat->items()->whereKey($it['id'])->first() : null;

                    $file = $request->file("cats.$ci.items.$ii.image");
                    if ($file) {
                        $request->validate(["cats.$ci.items.$ii.image" => ['image', 'max:2048']]);
                        $itemAttrs['image_path'] = $file->store('tagtoa/menu-items', 'public');
                    } elseif (! empty($it['remove_image'])) {
                        $itemAttrs['image_path'] = null;
                    }

                    $nouveau = $item === null;
                    $item ? $item->update($itemAttrs) : $item = $cat->items()->create($itemAttrs);
                    $keepItems[] = $item->id;

                    // Le stock ne s'écrit pas, il se journalise : le patron
                    // tape ce qu'il a sur l'étagère, le service en déduit
                    // l'écart. Sans cela, corriger un stock depuis le menu
                    // laisserait un trou dans l'historique — là même où le
                    // commerce cherchera plus tard ce qui a disparu.
                    $this->noteLeStock($item, $it, $nouveau);

                    // Le marqueur est posé par le formulaire : sans lui, la
                    // ligne n'a pas porté ses options (envoi tronqué, appel
                    // partiel) et on n'y touche pas plutôt que de les effacer.
                    if (! empty($it['options_sent'])) {
                        $this->syncItemOptions($item, $it['options'] ?? []);
                    }
                }
            }

            // ENREGISTRER NE SUPPRIME JAMAIS — ni un article, ni une catégorie.
            //
            // Le formulaire effaçait tout ce qui n'était pas renvoyé. Or un envoi
            // peut être incomplet sans que personne le veuille : connexion
            // coupée, deux personnes qui modifient en même temps, et surtout
            // max_input_vars côté PHP, qui TRONQUE $_POST en silence dès qu'un
            // menu devient gros. Le marchand cliquait « Enregistrer » et perdait
            // la moitié de sa carte.
            //
            // Depuis B-3 c'est pire : la caisse vend le catalogue du menu, donc
            // l'article effacé emportait son stock et disparaissait du comptoir.
            //
            // Retirer un plat de la vente sans rien perdre : l'interrupteur
            // « Disponible ». Le supprimer vraiment : la corbeille, une action
            // à part, confirmée.
        });
    }

    /** Synchronise les groupes d'options + choix d'un item (cats[][items][][options][][choices][]). */
    protected function syncItemOptions(Item $item, array $options): void
    {
        $keepOptions = [];
        foreach ($options as $oi => $o) {
            if (empty($o['name'])) {
                continue;
            }
            $optAttrs = [
                'name'     => $o['name'],
                'required' => ! empty($o['required']),
                'multiple' => ! empty($o['multiple']),
                'sort'     => (int) $oi,
            ];
            $opt = ! empty($o['id']) ? $item->options()->whereKey($o['id'])->first() : null;
            $opt ? $opt->update($optAttrs) : $opt = $item->options()->create($optAttrs);
            $keepOptions[] = $opt->id;

            $keepChoices = [];
            foreach (($o['choices'] ?? []) as $chi => $ch) {
                if (empty($ch['label'])) {
                    continue;
                }
                $chAttrs = [
                    'label'       => $ch['label'],
                    'price_delta' => round((float) ($ch['price_delta'] ?? 0), 2),
                    'sort'        => (int) $chi,
                ];
                $choice = ! empty($ch['id']) ? $opt->choices()->whereKey($ch['id'])->first() : null;
                $choice ? $choice->update($chAttrs) : $choice = $opt->choices()->create($chAttrs);
                $keepChoices[] = $choice->id;
            }
            $opt->choices()->whereNotIn('id', $keepChoices ?: [0])->delete();
        }
        $item->options()->whereNotIn('id', $keepOptions ?: [0])->delete();
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->where('tenant_id', Tenant::id())->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function payPages()
    {
        return PaymentPage::where('tenant_id', Tenant::id())->get(['id', 'title', 'alias']);
    }
}
