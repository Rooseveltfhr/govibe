<?php

namespace App\Http\Controllers\Admin\Histoire;

use App\Http\Controllers\Controller;
use App\Models\Histoire\HistoricalSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoricalSiteController extends Controller
{
    public function index()
    {
        $sites = HistoricalSite::orderBy('name')->get();

        return view('admin.histoire.sites.index', compact('sites'));
    }

    public function create()
    {
        $site = new HistoricalSite;

        return view('admin.histoire.sites.form', compact('site'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        HistoricalSite::create($data);

        return redirect()->route('admin.histoire.sites.index')->with('status', 'Lieu historique créé.');
    }

    public function edit(HistoricalSite $site)
    {
        return view('admin.histoire.sites.form', compact('site'));
    }

    public function update(Request $request, HistoricalSite $site): RedirectResponse
    {
        $data = $site->applyVerificationStamp($this->validated($request), $request->user());

        $site->update($data);

        return redirect()->route('admin.histoire.sites.index')->with('status', 'Lieu historique mis à jour.');
    }

    public function destroy(HistoricalSite $site): RedirectResponse
    {
        $site->delete();

        return redirect()->route('admin.histoire.sites.index')->with('status', 'Lieu historique supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
