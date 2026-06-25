<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Support\Locale;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA MENU — dashboard propriétaire (CRUD menu + catégories + items).
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $menus = Menu::where('tenant_id', Tenant::id())
            ->withCount(['items', 'categories'])
            ->latest()->paginate(12);

        return view('tagtoa::menu.index', compact('menus'));
    }

    public function create(): View
    {
        return view('tagtoa::menu.form', [
            'menu'     => new Menu(['theme' => 'light', 'accent_color' => '#0055FF', 'currency' => Locale::currencyFor()]),
            'vcards'   => $this->vcards(),
            'payPages' => $this->payPages(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMenu($request);
        $menu = new Menu($data);
        $menu->tenant_id = Tenant::id();
        $menu->alias = $data['alias'] ?: Menu::generateAlias($data['name'] ?? 'menu');
        $this->handleUploads($menu, $request);
        $menu->save();
        $this->syncContent($menu, $request);

        return redirect()->route('tagtoa.menu.dashboard.edit', $menu->id)
            ->with('success', __('Menu créé. Ajoutez vos catégories et produits.'));
    }

    public function edit(int $id): View
    {
        $menu = $this->own($id, ['categories.items']);

        return view('tagtoa::menu.form', [
            'menu'     => $menu,
            'vcards'   => $this->vcards(),
            'payPages' => $this->payPages(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $menu = $this->own($id);
        $data = $this->validateMenu($request, $menu->id);
        $data['alias'] = $data['alias'] ?: $menu->alias;
        $menu->fill($data);
        $this->handleUploads($menu, $request);
        $menu->save();
        $this->syncContent($menu, $request);

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

    public function setStatus(Request $request, int $orderId): RedirectResponse
    {
        $order = $this->ownOrder($orderId);
        $data = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);
        $order->update(['status' => $data['status']]);

        return back()->with('success', __('Commande mise à jour.'));
    }

    public function markPaid(int $orderId): RedirectResponse
    {
        $order = $this->ownOrder($orderId);
        app(MenuOrderService::class)->markPaid($order);

        return back()->with('success', __('Paiement effectué.'));
    }

    protected function ownOrder(int $id): Order
    {
        return Order::whereHas('menu', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
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
        $ownVcardIds = $this->vcards()->pluck('id')->all();
        $ownPayIds   = $this->payPages()->pluck('id')->all();

        return $request->validate([
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
            'logo'             => ['nullable', 'image', 'max:2048'],
            'cover'            => ['nullable', 'image', 'max:4096'],
        ]);
    }

    /** Synchronise catégories + items depuis le formulaire imbriqué (cats[][items][]). */
    protected function syncContent(Menu $menu, Request $request): void
    {
        $cats = $request->input('cats', []);
        $keepCats = [];

        DB::transaction(function () use ($menu, $cats, &$keepCats) {
            foreach (array_values($cats) as $ci => $c) {
                if (empty($c['name'])) {
                    continue;
                }
                $catAttrs = [
                    'name'      => $c['name'],
                    'icon'      => $c['icon'] ?? null,
                    'sort'      => $ci,
                    'is_active' => true,
                ];
                $cat = ! empty($c['id']) ? $menu->categories()->whereKey($c['id'])->first() : null;
                $cat ? $cat->update($catAttrs) : $cat = $menu->categories()->create($catAttrs);
                $keepCats[] = $cat->id;

                $keepItems = [];
                foreach (array_values($c['items'] ?? []) as $ii => $it) {
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
                        'is_featured'  => ! empty($it['is_featured']),
                        'is_available' => ! isset($it['is_available']) ? true : (bool) $it['is_available'],
                        'sort'         => $ii,
                    ];
                    $item = ! empty($it['id']) ? $cat->items()->whereKey($it['id'])->first() : null;
                    $item ? $item->update($itemAttrs) : $item = $cat->items()->create($itemAttrs);
                    $keepItems[] = $item->id;
                }
                $cat->items()->whereNotIn('id', $keepItems ?: [0])->delete();
            }
            $menu->categories()->whereNotIn('id', $keepCats ?: [0])->delete();
        });
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
