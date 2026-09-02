<?php

namespace App\Http\Controllers\Admin\Histoire;

use App\Http\Controllers\Controller;
use App\Models\Histoire\HistoricalEvent;
use App\Models\Histoire\HistoricalPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoricalEventController extends Controller
{
    public function index()
    {
        $events = HistoricalEvent::with('period')->orderBy('circa_year')->orderBy('happened_on')->get();

        return view('admin.histoire.events.index', compact('events'));
    }

    public function create()
    {
        $periods = HistoricalPeriod::orderBy('display_order')->get();
        $event = new HistoricalEvent;

        return view('admin.histoire.events.form', compact('periods', 'event'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        HistoricalEvent::create($data);

        return redirect()->route('admin.histoire.events.index')->with('status', 'Événement créé.');
    }

    public function edit(HistoricalEvent $event)
    {
        $periods = HistoricalPeriod::orderBy('display_order')->get();

        return view('admin.histoire.events.form', compact('periods', 'event'));
    }

    public function update(Request $request, HistoricalEvent $event): RedirectResponse
    {
        $data = $event->applyVerificationStamp($this->validated($request), $request->user());

        $event->update($data);

        return redirect()->route('admin.histoire.events.index')->with('status', 'Événement mis à jour.');
    }

    public function destroy(HistoricalEvent $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('admin.histoire.events.index')->with('status', 'Événement supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'historical_period_id' => ['nullable', 'exists:historical_periods,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'happened_on' => ['nullable', 'date'],
            'circa_year' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
