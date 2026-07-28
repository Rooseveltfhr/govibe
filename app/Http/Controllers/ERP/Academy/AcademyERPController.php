<?php

namespace App\Http\Controllers\ERP\Academy;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\Inscription;
use Illuminate\Http\Request;

class AcademyERPController extends Controller
{
    public function index()
    {
        $stats = [
            'formations'   => $this->safeCount(fn() => Formation::count()),
            'inscriptions' => $this->safeCount(fn() => Inscription::count()),
            'presents'     => $this->safeCount(fn() => Inscription::where('present', true)->count()),
            'active'       => $this->safeCount(fn() => Formation::where('active', true)->count()),
        ];

        $formations = $this->safeQuery(
            fn() => Formation::withCount('inscriptions')->orderByDesc('created_at')->get(),
            collect()
        );

        $recentInscriptions = $this->safeQuery(
            fn() => Inscription::with('formation')->orderByDesc('created_at')->limit(10)->get(),
            collect()
        );

        return view('erp.academy.index', compact('stats', 'formations', 'recentInscriptions'));
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
