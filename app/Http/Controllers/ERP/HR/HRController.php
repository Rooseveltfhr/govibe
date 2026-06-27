<?php

namespace App\Http\Controllers\ERP\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class HRController extends Controller
{
    public function index()
    {
        $stats = [
            'total'    => $this->safeCount(fn() => Employee::count()),
            'active'   => $this->safeCount(fn() => Employee::where('status', 'active')->count()),
            'onLeave'  => $this->safeCount(fn() => Employee::where('status', 'on_leave')->count()),
            'new'      => $this->safeCount(fn() => Employee::whereMonth('hired_at', now()->month)->count()),
        ];

        $employees = $this->safeQuery(fn() => Employee::orderBy('name')->paginate(20), collect());

        return view('erp.hr.index', compact('stats', 'employees'));
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
