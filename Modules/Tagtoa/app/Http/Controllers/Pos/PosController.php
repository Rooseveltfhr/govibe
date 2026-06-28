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
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — caisse tactile + back-office.
 */
class PosController extends Controller
{
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

    protected function own(int $id, array $with = []): Terminal
    {
        return Terminal::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
