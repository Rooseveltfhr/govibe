<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function about()
    {
        return view('about');
    }

    public function services()
    {
        return view('services.index');
    }

    public function coworking()
    {
        return view('services.coworking');
    }

    public function startupLab()
    {
        return view('services.startup-lab');
    }

    public function academy()
    {
        return view('academy');
    }

    public function callCenter()
    {
        return view('services.call-center');
    }

    public function programmes()
    {
        return view('services.programmes');
    }

    public function tarifs(): \Illuminate\View\View
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($plans->isEmpty()) {
            $plans = collect(SubscriptionPlan::defaults())->map(fn($p) => (object) $p);
        }

        return view('tarifs', compact('plans'));
    }
}
