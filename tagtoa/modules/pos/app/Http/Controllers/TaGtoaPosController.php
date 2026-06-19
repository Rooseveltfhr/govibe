<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaPosSale;
use App\Models\TaGtoaPosTerminal;
use App\Services\TaGtoaPosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TAGTOA POS — caisse tactile + back-office.
 *
 *   GET  /tagtoa/pos                  -> index (liste terminaux)
 *   GET  /tagtoa/pos/{id}/register    -> register (interface caisse, offline-first)
 *   POST /tagtoa/pos/{id}/sale        -> sale (JSON, idempotent)
 *   POST /tagtoa/pos/{id}/sync        -> sync (lot offline)
 *   GET  /tagtoa/pos/{id}/report      -> report (Z journalier + historique)
 *   ... + CRUD terminal/produits/cash
 */
class TaGtoaPosController extends Controller
{
    public function __construct(protected TaGtoaPosService $service)
    {
    }

    public function index(): View
    {
        $terminals = TaGtoaPosTerminal::withCount('products')->latest()->get();

        return view('tagtoa.pos.index', compact('terminals'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $terminal = new TaGtoaPosTerminal($data);
        $terminal->tenant_id = function_exists('getLogInTenantId') ? getLogInTenantId() : null;
        $terminal->save();

        return redirect()->route('tagtoa.pos.products', $terminal->id)->with('success', __('Caisse créée.'));
    }

    /** Interface caisse tactile (offline-first). */
    public function register(int $id): View
    {
        $terminal = TaGtoaPosTerminal::with('activeProducts')->findOrFail($id);

        return view('tagtoa.pos.register', [
            'terminal' => $terminal,
            'products' => $terminal->activeProducts,
            'methods'  => TaGtoaPosSale::PAYMENT_METHODS,
        ]);
    }

    public function sale(Request $request, int $id): JsonResponse
    {
        $terminal = TaGtoaPosTerminal::findOrFail($id);

        $data = $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.name'     => ['required', 'string', 'max:120'],
            'items.*.price'    => ['required', 'numeric', 'min:0'],
            'items.*.qty'      => ['required', 'integer', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'discount'         => ['nullable', 'numeric', 'min:0'],
            'payments'         => ['nullable', 'array'],
            'customer_phone'   => ['nullable', 'string', 'max:40'],
            'client_uuid'      => ['nullable', 'string', 'max:64'],
        ]);

        $sale = $this->service->recordSale($terminal, $data);

        return response()->json([
            'ok'        => true,
            'reference' => $sale->reference,
            'total'     => (float) $sale->total,
            'sale_id'   => $sale->id,
        ]);
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $terminal = TaGtoaPosTerminal::findOrFail($id);
        $sales    = $request->input('sales', []);
        $results  = [];

        foreach ($sales as $payload) {
            try {
                $sale      = $this->service->recordSale($terminal, $payload);
                $results[] = ['client_uuid' => $payload['client_uuid'] ?? null, 'ok' => true, 'reference' => $sale->reference];
            } catch (\Throwable $e) {
                $results[] = ['client_uuid' => $payload['client_uuid'] ?? null, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    /** Rapport Z journalier + historique. */
    public function report(int $id, Request $request): View
    {
        $terminal = TaGtoaPosTerminal::findOrFail($id);
        $date     = $request->date('date') ?: now();

        $sales = $terminal->sales()
            ->whereDate('sold_at', $date)
            ->where('status', TaGtoaPosSale::STATUS_COMPLETED)
            ->latest()
            ->get();

        // Z : totaux + ventilation par méthode de paiement.
        $byMethod = [];
        foreach ($sales as $s) {
            foreach ((array) $s->payments as $p) {
                $m = $p['method'] ?? 'cash';
                $byMethod[$m] = ($byMethod[$m] ?? 0) + (float) ($p['amount'] ?? 0);
            }
        }

        $z = [
            'date'      => $date->format('Y-m-d'),
            'count'     => $sales->count(),
            'total'     => $sales->sum('total'),
            'by_method' => $byMethod,
        ];

        return view('tagtoa.pos.report', compact('terminal', 'sales', 'z'));
    }

    /* --------------------------------------------------------------- products */

    public function products(int $id): View
    {
        $terminal = TaGtoaPosTerminal::with('products')->findOrFail($id);

        return view('tagtoa.pos.products', compact('terminal'));
    }

    public function saveProducts(Request $request, int $id): RedirectResponse
    {
        $terminal = TaGtoaPosTerminal::findOrFail($id);
        $rows     = $request->input('products', []);
        $keep     = [];

        foreach ($rows as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $attrs = [
                'name'      => $row['name'],
                'price'     => (float) ($row['price'] ?? 0),
                'emoji'     => $row['emoji'] ?? null,
                'color'     => $row['color'] ?? '#0055FF',
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

    public function cash(Request $request, int $id): RedirectResponse
    {
        $terminal = TaGtoaPosTerminal::findOrFail($id);
        $data     = $request->validate([
            'type'   => ['required', 'in:open,in,out,close'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $this->service->cashMovement($terminal, $data['type'], (float) $data['amount'], $data['reason'] ?? null);

        return back()->with('success', __('Mouvement de caisse enregistré.'));
    }
}
