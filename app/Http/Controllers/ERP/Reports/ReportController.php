<?php

namespace App\Http\Controllers\ERP\Reports;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;

        $monthlyRevenue = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyRevenue[] = [
                'month'  => now()->month($m)->format('M'),
                'amount' => $this->safeSum(
                    fn() => Invoice::where('status', 'paid')
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $m)
                        ->sum('total_amount')
                ),
            ];
        }

        $stats = [
            'totalRevenue'  => $this->safeSum(fn() => Invoice::where('status', 'paid')->whereYear('created_at', $currentYear)->sum('total_amount')),
            'totalClients'  => $this->safeCount(fn() => Client::count()),
            'totalProjects' => $this->safeCount(fn() => Project::count()),
            'pendingInvoices' => $this->safeCount(fn() => Invoice::where('status', 'sent')->count()),
        ];

        return view('erp.reports.index', compact('stats', 'monthlyRevenue', 'currentYear'));
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeSum(callable $fn, float $default = 0): float
    {
        try { return (float) $fn(); } catch (\Exception $e) { return $default; }
    }
}
