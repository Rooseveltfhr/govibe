<?php

namespace App\Http\Controllers\ERP\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $stats = [
            'total'    => $this->safeCount(fn() => InventoryItem::count()),
            'lowStock' => $this->safeCount(fn() => InventoryItem::whereColumn('quantity', '<=', 'min_quantity')->count()),
            'value'    => $this->safeSum(fn() => InventoryItem::selectRaw('SUM(quantity * unit_price) as total')->value('total')),
        ];

        $items = $this->safeQuery(
            fn() => InventoryItem::orderBy('name')->paginate(20),
            collect()
        );

        return view('erp.inventory.index', compact('stats', 'items'));
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeSum(callable $fn, float $default = 0): float
    {
        try { return (float) $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
