<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_clients'    => $this->safeCount('clients'),
            'active_projects'  => $this->safeCount('projects', ['status' => 'active']),
            'monthly_revenue'  => $this->safeSum('invoices', 'total', ['status' => 'paid']),
            'total_employees'  => $this->safeCount('employees', ['status' => 'active']),
        ];

        $recentProjects = $this->safeQuery(fn() =>
            Project::with('client')->latest()->take(5)->get()
        );

        $recentClients = $this->safeQuery(fn() =>
            Client::latest()->take(6)->get()
        );

        return view('erp.dashboard.index', compact('stats', 'recentProjects', 'recentClients'));
    }

    private function safeCount(string $table, array $where = []): int
    {
        try {
            $q = DB::table($table);
            foreach ($where as $col => $val) $q->where($col, $val);
            return $q->count();
        } catch (\Exception) { return 0; }
    }

    private function safeSum(string $table, string $col, array $where = []): float
    {
        try {
            $q = DB::table($table);
            foreach ($where as $k => $v) $q->where($k, $v);
            return (float) $q->sum($col);
        } catch (\Exception) { return 0; }
    }

    private function safeQuery(callable $fn): mixed
    {
        try { return $fn(); } catch (\Exception) { return collect(); }
    }
}
