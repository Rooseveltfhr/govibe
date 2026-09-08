<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderByDesc('starts_at')->get();

        return view('admin.evenements.index', compact('events'));
    }

    public function create()
    {
        $event = new Event;

        return view('admin.evenements.form', compact('event'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Event::create($data);

        return redirect()->route('admin.evenements.index')->with('status', 'Événement créé.');
    }

    public function edit(Event $event)
    {
        return view('admin.evenements.form', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $event->applyVerificationStamp($this->validated($request), $request->user());

        $event->update($data);

        return redirect()->route('admin.evenements.index')->with('status', 'Événement mis à jour.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('admin.evenements.index')->with('status', 'Événement supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'description' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
