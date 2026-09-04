<?php

namespace App\Http\Controllers\Admin\Histoire;

use App\Http\Controllers\Controller;
use App\Models\Histoire\HistoricalPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoricalPeriodController extends Controller
{
    public function index()
    {
        $periods = HistoricalPeriod::orderBy('display_order')->get();

        return view('admin.histoire.periods.index', compact('periods'));
    }

    public function create()
    {
        $period = new HistoricalPeriod;

        return view('admin.histoire.periods.form', compact('period'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        HistoricalPeriod::create($data);

        return redirect()->route('admin.histoire.periods.index')->with('status', 'Période créée.');
    }

    public function edit(HistoricalPeriod $period)
    {
        return view('admin.histoire.periods.form', compact('period'));
    }

    public function update(Request $request, HistoricalPeriod $period): RedirectResponse
    {
        $data = $period->applyVerificationStamp($this->validated($request), $request->user());

        $period->update($data);

        return redirect()->route('admin.histoire.periods.index')->with('status', 'Période mise à jour.');
    }

    public function destroy(HistoricalPeriod $period): RedirectResponse
    {
        $period->delete();

        return redirect()->route('admin.histoire.periods.index')->with('status', 'Période supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'start_year' => ['nullable', 'integer'],
            'end_year' => ['nullable', 'integer'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
