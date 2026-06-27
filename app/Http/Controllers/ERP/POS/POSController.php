<?php

namespace App\Http\Controllers\ERP\POS;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class POSController extends Controller
{
    public function index()
    {
        $services = $this->safeQuery(
            fn() => Service::with('category')->where('is_active', true)->orderBy('name')->get(),
            collect()
        );

        $todaySales  = collect();
        $todayTotal  = 0;

        return view('erp.pos.index', compact('services', 'todaySales', 'todayTotal'));
    }

    public function sale(Request $request)
    {
        $data = $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'required|exists:services,id',
            'items.*.qty'    => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,moncash,natcash,bank,card,paypal',
            'client_name'    => 'nullable|string|max:255',
        ]);

        $total = 0;
        foreach ($data['items'] as $item) {
            $service = Service::findOrFail($item['id']);
            $total += $service->price * $item['qty'];
        }

        return response()->json(['success' => true, 'total' => $total]);
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
