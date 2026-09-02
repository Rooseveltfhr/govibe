<?php

namespace App\Http\Controllers;

use App\Models\Histoire\HistoricalPeriod;

class HistoireController extends Controller
{
    public function index()
    {
        $periods = HistoricalPeriod::with(['events', 'figures'])
            ->orderBy('display_order')
            ->orderBy('start_year')
            ->get();

        return view('histoire.index', compact('periods'));
    }
}
