<?php

namespace App\Http\Controllers\Admin\Histoire;

use App\Http\Controllers\Controller;
use App\Models\Histoire\HistoricalFigure;
use App\Models\Histoire\HistoricalPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoricalFigureController extends Controller
{
    public function index()
    {
        $figures = HistoricalFigure::with('period')->orderBy('name')->get();

        return view('admin.histoire.figures.index', compact('figures'));
    }

    public function create()
    {
        $periods = HistoricalPeriod::orderBy('display_order')->get();
        $figure = new HistoricalFigure;

        return view('admin.histoire.figures.form', compact('periods', 'figure'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        HistoricalFigure::create($data);

        return redirect()->route('admin.histoire.figures.index')->with('status', 'Personnage créé.');
    }

    public function edit(HistoricalFigure $figure)
    {
        $periods = HistoricalPeriod::orderBy('display_order')->get();

        return view('admin.histoire.figures.form', compact('periods', 'figure'));
    }

    public function update(Request $request, HistoricalFigure $figure): RedirectResponse
    {
        $data = $figure->applyVerificationStamp($this->validated($request), $request->user());

        $figure->update($data);

        return redirect()->route('admin.histoire.figures.index')->with('status', 'Personnage mis à jour.');
    }

    public function destroy(HistoricalFigure $figure): RedirectResponse
    {
        $figure->delete();

        return redirect()->route('admin.histoire.figures.index')->with('status', 'Personnage supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'historical_period_id' => ['nullable', 'exists:historical_periods,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'bio' => ['nullable', 'string'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
