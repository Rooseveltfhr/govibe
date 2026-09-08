<?php

namespace App\Http\Controllers;

use App\Models\Histoire\HistoricalSite;

class HistoricalSiteController extends Controller
{
    public function index()
    {
        $sites = HistoricalSite::orderBy('name')->get();

        return view('lieux-historiques.index', compact('sites'));
    }

    public function show(string $slug)
    {
        $site = HistoricalSite::where('slug', $slug)->firstOrFail();

        return view('lieux-historiques.show', compact('site'));
    }
}
