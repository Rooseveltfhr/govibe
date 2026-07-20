<?php

namespace Modules\Tagtoa\App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Store\Order;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Store\StoreOrderService;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA STORE — back-office boutique (côté marchand).
 */
class DashboardController extends Controller
{
    use EnforcesPlan;

    public function index(): View
    {
        $stores = Store::where('tenant_id', Tenant::id())
            ->withCount(['products', 'orders'])->latest()->paginate(20);

        return view('tagtoa::store.index', compact('stores'));
    }

    public function create(): View
    {
        return view('tagtoa::store.form', ['store' => new Store, 'payPages' => $this->payPages()]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($r = $this->planGuard('store')) {
            return $r;
        }

        $data = $this->validateStore($request);
        $store = new Store($data);
        $store->tenant_id = Tenant::id();
        $store->alias = $data['alias'] ?? Store::generateAlias($data['name'] ?? 'boutik');
        $this->handleUploads($store, $request);
        $store->save();
        $this->syncProducts($store, $request);

        return redirect()->route('tagtoa.store.dashboard.edit', $store->id)
            ->with('success', __('Boutique créée. Ajoutez vos produits.'));
    }

    public function edit(int $id): View
    {
        $store = $this->own($id, ['products']);

        return view('tagtoa::store.form', ['store' => $store, 'payPages' => $this->payPages()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $store = $this->own($id);
        $data = $this->validateStore($request, $store->id);
        $store->fill($data);
        if (! empty($data['alias'])) {
            $store->alias = $data['alias'];
        }
        $this->handleUploads($store, $request);
        $store->save();
        $this->syncProducts($store, $request);

        return back()->with('success', __('Boutique mise à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->own($id)->delete();

        return redirect()->route('tagtoa.store.dashboard.index')->with('success', __('Boutique supprimée.'));
    }

    public function orders(int $id): View
    {
        $store = $this->own($id);
        $orders = $store->orders()->withCount('items')->latest()->paginate(20);
        $analytics = [
            'revenue' => $store->orders()->where('payment_status', 'paid')->sum('total'),
            'orders'  => $store->orders()->count(),
            'paid'    => $store->orders()->where('payment_status', 'paid')->count(),
        ];

        return view('tagtoa::store.orders', compact('store', 'orders', 'analytics'));
    }

    public function setStatus(Request $request, int $orderId): RedirectResponse
    {
        $order = $this->ownOrder($orderId);
        $data = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);
        $order->update(['status' => $data['status']]);
        app(AuditService::class)->log('store_order_status', $order, $order->reference.' → '.$data['status']);

        return back()->with('success', __('Statut mis à jour.'));
    }

    public function markPaid(int $orderId): RedirectResponse
    {
        $order = $this->ownOrder($orderId);
        app(StoreOrderService::class)->markPaid($order);
        app(AuditService::class)->log('store_order_paid', $order, $order->reference);

        return back()->with('success', __('Commande encaissée.'));
    }

    /* ---------- helpers ---------- */

    protected function ownOrder(int $id): Order
    {
        return Order::whereHas('store', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
    }

    protected function own(int $id, array $with = []): Store
    {
        return Store::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function handleUploads(Store $store, Request $request): void
    {
        if ($request->hasFile('logo')) {
            $store->logo_path = $request->file('logo')->store('tagtoa/store-logos', 'public');
        }
        if ($request->hasFile('cover')) {
            $store->cover_path = $request->file('cover')->store('tagtoa/store-covers', 'public');
        }
    }

    protected function validateStore(Request $request, ?int $ignoreId = null): array
    {
        $ownPayIds = $this->payPages()->pluck('id')->all();

        return $request->validate([
            'name'          => ['required', 'string', 'max:160'],
            'alias'         => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_stores,alias'.($ignoreId ? ','.$ignoreId : '')],
            'tagline'       => ['nullable', 'string', 'max:160'],
            'description'   => ['nullable', 'string', 'max:800'],
            'currency'      => ['nullable', 'string', 'max:8'],
            'whatsapp'      => ['nullable', 'string', 'max:40'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'address'       => ['nullable', 'string', 'max:200'],
            'delivery_note' => ['nullable', 'string', 'max:400'],
            'pay_page_id'   => ['nullable', 'integer', Rule::in($ownPayIds)],
            'accent_color'  => ['nullable', 'string', 'max:16'],
            'is_published'  => ['nullable', 'boolean'],
            'logo'          => ['nullable', 'image', 'max:2048'],
            'cover'         => ['nullable', 'image', 'max:4096'],
        ]);
    }

    /** Synchronise les produits depuis le formulaire répétable (products[]). */
    protected function syncProducts(Store $store, Request $request): void
    {
        $products = $request->input('products', []);
        $keep = [];

        DB::transaction(function () use ($store, $request, $products, &$keep) {
            foreach (array_values($products) as $i => $p) {
                if (empty($p['name'])) {
                    continue;
                }
                $attrs = [
                    'name'          => $p['name'],
                    'description'   => $p['description'] ?? null,
                    'price'         => round((float) ($p['price'] ?? 0), 2),
                    'compare_price' => (! isset($p['compare_price']) || $p['compare_price'] === '') ? null : round((float) $p['compare_price'], 2),
                    'category'      => $p['category'] ?? null,
                    'stock'         => (! isset($p['stock']) || $p['stock'] === '') ? null : max(0, (int) $p['stock']),
                    'is_available'  => ! isset($p['is_available']) ? true : (bool) $p['is_available'],
                    'is_featured'   => ! empty($p['is_featured']),
                    'sort'          => $i,
                ];

                $product = ! empty($p['id']) ? $store->products()->whereKey($p['id'])->first() : null;
                $product ? $product->update($attrs) : $product = $store->products()->create($attrs);

                // Image par produit (optionnelle) : products[i][image]
                $file = $request->file("products.$i.image");
                if ($file) {
                    $product->update(['image_path' => $file->store('tagtoa/store-products', 'public')]);
                }

                $keep[] = $product->id;
            }
            $store->products()->whereNotIn('id', $keep ?: [0])->delete();
        });
    }

    protected function payPages()
    {
        try {
            return PaymentPage::where('tenant_id', Tenant::id())->get(['id', 'title', 'alias']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
