<?php

namespace App\Http\Controllers\ERP\POS;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PosPayment;
use App\Models\PosSession;
use App\Models\PosTransaction;
use App\Models\PosTransactionItem;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class POSController extends Controller
{
    public function index(): View
    {
        $services = $this->safe(
            fn() => Service::with('category')->where('is_active', true)->orderBy('name')->get(),
            collect()
        );

        $session = $this->safe(fn() => $this->currentSession(), null);

        $todaySales = collect();
        $todayTotal = 0;

        if ($session) {
            $todaySales = $this->safe(
                fn() => PosTransaction::with(['items.service', 'payments'])
                    ->where('session_id', $session->id)
                    ->where('status', 'completed')
                    ->latest()
                    ->get(),
                collect()
            );
            $todayTotal = $todaySales->sum('total');
        }

        return view('erp.pos.index', compact('services', 'todaySales', 'todayTotal', 'session'));
    }

    public function sale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|integer',
            'items.*.qty'      => 'required|integer|min:1',
            'discount'         => 'nullable|numeric|min:0',
            'payment_method'   => 'required|in:cash,moncash,natcash,bank,card,paypal,zelle,usdt',
            'client_name'      => 'nullable|string|max:255',
        ]);

        try {
            $transaction = DB::transaction(function () use ($data) {
                $session = $this->currentSession();

                $subtotal = 0;
                $lines = [];
                foreach ($data['items'] as $row) {
                    $service = Service::findOrFail($row['id']);
                    $qty     = (int) $row['qty'];
                    $price   = (float) $service->price;
                    $subtotal += $price * $qty;
                    $lines[] = compact('service', 'qty', 'price');
                }

                $discount = max(0, (float) ($data['discount'] ?? 0));
                $total    = max(0, $subtotal - $discount);

                $tx = PosTransaction::create([
                    'reference'  => $this->generateRef(),
                    'session_id' => $session->id,
                    'client_id'  => null,
                    'subtotal'   => $subtotal,
                    'tax'        => 0,
                    'discount'   => $discount,
                    'total'      => $total,
                    'status'     => 'completed',
                    'served_by'  => Auth::id(),
                ]);

                foreach ($lines as $line) {
                    PosTransactionItem::create([
                        'transaction_id' => $tx->id,
                        'service_id'     => $line['service']->id,
                        'description'    => $line['service']->name,
                        'quantity'       => $line['qty'],
                        'unit_price'     => $line['price'],
                        'total'          => $line['price'] * $line['qty'],
                    ]);
                }

                PosPayment::create([
                    'transaction_id' => $tx->id,
                    'method'         => $data['payment_method'],
                    'amount'         => $total,
                    'reference'      => null,
                ]);

                $tx->load(['items.service', 'payments']);

                return $tx;
            });

            return response()->json([
                'success'     => true,
                'reference'   => $transaction->reference,
                'subtotal'    => (float) $transaction->subtotal,
                'discount'    => (float) $transaction->discount,
                'total'       => (float) $transaction->total,
                'payment'     => $data['payment_method'],
                'client_name' => $data['client_name'] ?? null,
                'items'       => $transaction->items->map(fn($i) => [
                    'name'       => $i->description,
                    'qty'        => (int) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'line_total' => (float) $i->total,
                ]),
                'sold_at' => now()->format('d/m/Y H:i'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function receipt(string $ref): View
    {
        $tx = PosTransaction::with(['items.service', 'payments', 'servedBy'])
            ->where('reference', $ref)
            ->firstOrFail();

        return view('erp.pos.receipt', compact('tx'));
    }

    /* ── Helpers ─────────────────────────────────────────────────── */

    private function currentSession(): PosSession
    {
        $userId = Auth::id();

        $session = PosSession::where('opened_by', $userId)
            ->where('status', 'open')
            ->whereDate('opened_at', today())
            ->first();

        if (! $session) {
            $session = PosSession::create([
                'opened_by'      => $userId,
                'opened_at'      => now(),
                'opening_amount' => 0,
                'status'         => 'open',
            ]);
        }

        return $session;
    }

    private function generateRef(): string
    {
        do {
            $ref = 'GVP-' . strtoupper(Str::random(6));
        } while (PosTransaction::where('reference', $ref)->exists());

        return $ref;
    }

    private function safe(callable $fn, $default)
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }
}
