<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — caisse tactile + back-office.
 */
class PosController extends Controller
{
    use EnforcesPlan;

    public function __construct(protected PosService $service)
    {
    }

    public function index(): View
    {
        $terminals = Terminal::where('tenant_id', Tenant::id())->withCount('products')->latest()->get();

        return view('tagtoa::pos.index', compact('terminals'));
    }

    public function store(Request $request): RedirectResponse
    {
        if ($r = $this->planGuard('pos')) {
            return $r;
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'currency' => ['nullable', 'string', 'max:10']]);
        $terminal = new Terminal($data);
        $terminal->tenant_id = Tenant::id();
        $terminal->save();

        return redirect()->route('tagtoa.pos.products', $terminal->id)->with('success', __('Caisse créée.'));
    }

    public function register(int $id): View
    {
        $terminal = $this->own($id, ['activeProducts']);

        return view('tagtoa::pos.register', ['terminal' => $terminal, 'products' => $terminal->activeProducts, 'methods' => Sale::METHODS]);
    }

    public function sale(Request $request, int $id): JsonResponse
    {
        $terminal = $this->own($id);
        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.name'       => ['required', 'string', 'max:120'],
            'items.*.price'      => ['required', 'numeric', 'min:0'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'payments'           => ['nullable', 'array'],
            'customer_phone'     => ['nullable', 'string', 'max:40'],
            'client_uuid'        => ['nullable', 'string', 'max:64'],
        ]);

        $sale = $this->service->recordSale($terminal, $data);

        return response()->json(['ok' => true, 'reference' => $sale->reference, 'total' => (float) $sale->total]);
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $terminal = $this->own($id);
        $results = [];
        foreach ($request->input('sales', []) as $payload) {
            try {
                $sale = $this->service->recordSale($terminal, $payload);
                $results[] = ['client_uuid' => $payload['client_uuid'] ?? null, 'ok' => true, 'reference' => $sale->reference];
            } catch (\Throwable $e) {
                $results[] = ['client_uuid' => $payload['client_uuid'] ?? null, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function report(int $id, Request $request): View
    {
        $terminal = $this->own($id);
        $date = $request->date('date') ?: now();
        $sales = $terminal->sales()->whereDate('sold_at', $date)->where('status', 1)->latest()->get();

        $byMethod = [];
        foreach ($sales as $s) {
            foreach ((array) $s->payments as $p) {
                $m = $p['method'] ?? 'cash';
                $byMethod[$m] = ($byMethod[$m] ?? 0) + (float) ($p['amount'] ?? 0);
            }
        }
        $z = ['date' => $date->format('Y-m-d'), 'count' => $sales->count(), 'total' => $sales->sum('total'), 'by_method' => $byMethod];

        return view('tagtoa::pos.report', compact('terminal', 'sales', 'z'));
    }

    public function products(int $id): View
    {
        $terminal = $this->own($id, ['products']);

        return view('tagtoa::pos.products', compact('terminal'));
    }

    public function saveProducts(Request $request, int $id): RedirectResponse
    {
        $terminal = $this->own($id);
        $keep = [];
        foreach ($request->input('products', []) as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $attrs = [
                'name'      => $row['name'],
                'price'     => (float) ($row['price'] ?? 0),
                'emoji'     => $row['emoji'] ?? null,
                'color'     => $row['color'] ?? '#16A34A',
                'stock'     => ($row['stock'] ?? '') === '' ? null : (int) $row['stock'],
                'is_active' => ! empty($row['is_active']),
                'sort'      => (int) ($row['sort'] ?? $i),
            ];
            $p = ! empty($row['id']) ? $terminal->products()->whereKey($row['id'])->first() : null;
            $p ? $p->update($attrs) : $p = $terminal->products()->create($attrs);
            $keep[] = $p->id;
        }
        $terminal->products()->whereNotIn('id', $keep ?: [0])->delete();

        return back()->with('success', __('Produits enregistrés.'));
    }

    /* ---------- PWA (installable + offline) ---------- */

    /** Manifeste Web App de la caisse (par terminal). */
    public function manifest(int $id): JsonResponse
    {
        $terminal = $this->own($id);
        $scope = rtrim(url('/tagtoa/pos'), '/').'/';

        return response()->json([
            'name'             => $terminal->name.' — TAGTOA POS',
            'short_name'       => 'TAGTOA POS',
            'start_url'        => route('tagtoa.pos.register', $terminal->id),
            'scope'            => $scope,
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#0A0A0A',
            'theme_color'      => '#16A34A',
            'lang'             => app()->getLocale(),
            'icons'            => [
                ['src' => route('tagtoa.pos.icon'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ]);
    }

    /** Icône SVG (vectorielle, sans fichier binaire à publier). */
    public function icon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">'
            .'<rect width="512" height="512" rx="96" fill="#16A34A"/>'
            .'<path d="M300 96 154 288h86l-28 128 160-208h-92z" fill="#fff"/>'
            .'</svg>';

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** Service worker : cache l'enveloppe (app shell) pour un usage hors ligne. */
    public function serviceWorker()
    {
        $scope = rtrim(url('/tagtoa/pos'), '/').'/';
        $js = <<<JS
const CACHE = 'tagtoa-pos-v1';
self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
// Network-first pour la navigation (HTML), cache-first pour le reste. GET seulement.
self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).then(res => { const c = res.clone(); caches.open(CACHE).then(ca => ca.put(req, c)); return res; })
                      .catch(() => caches.match(req))
        );
        return;
    }
    e.respondWith(
        caches.match(req).then(hit => hit || fetch(req).then(res => {
            if (res && res.status === 200 && (res.type === 'basic' || res.type === 'cors')) {
                const c = res.clone(); caches.open(CACHE).then(ca => ca.put(req, c));
            }
            return res;
        }).catch(() => hit))
    );
});
JS;

        return response($js, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', $scope);
    }

    protected function own(int $id, array $with = []): Terminal
    {
        return Terminal::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
