<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index()
    {
        $activities = Activity::orderBy('title')->get();

        return view('admin.explorer.index', compact('activities'));
    }

    public function create()
    {
        $activity = new Activity;

        return view('admin.explorer.form', compact('activity'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Activity::create($data);

        return redirect()->route('admin.explorer.index')->with('status', 'Activité créée.');
    }

    public function edit(Activity $activity)
    {
        return view('admin.explorer.form', compact('activity'));
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $data = $activity->applyVerificationStamp($this->validated($request), $request->user());

        $activity->update($data);

        return redirect()->route('admin.explorer.index')->with('status', 'Activité mise à jour.');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $activity->delete();

        return redirect()->route('admin.explorer.index')->with('status', 'Activité supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'duration' => ['nullable', 'string', 'max:255'],
            'price_range' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
