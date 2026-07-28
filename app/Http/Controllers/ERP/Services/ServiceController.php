<?php

namespace App\Http\Controllers\ERP\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = $this->safeQuery(
            fn() => Service::with('category')->where('is_active', true)->orderBy('name')->paginate(20),
            collect()
        );

        $categories = $this->safeQuery(
            fn() => ServiceCategory::orderBy('name')->get(),
            collect()
        );

        return view('erp.services.index', compact('services', 'categories'));
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
