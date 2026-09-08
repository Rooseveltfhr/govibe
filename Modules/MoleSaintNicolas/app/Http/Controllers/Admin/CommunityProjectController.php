<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityProjectController extends Controller
{
    public function index()
    {
        $projects = CommunityProject::withCount('comments')->orderByDesc('created_at')->get();

        return view('admin.projets.index', compact('projects'));
    }

    public function create()
    {
        $project = new CommunityProject;

        return view('admin.projets.form', compact('project'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        CommunityProject::create($data);

        return redirect()->route('admin.projets.index')->with('status', 'Projet créé.');
    }

    public function edit(CommunityProject $projet)
    {
        return view('admin.projets.form', ['project' => $projet]);
    }

    public function update(Request $request, CommunityProject $projet): RedirectResponse
    {
        $data = $projet->applyVerificationStamp($this->validated($request), $request->user());

        $projet->update($data);

        return redirect()->route('admin.projets.index')->with('status', 'Projet mis à jour.');
    }

    public function destroy(CommunityProject $projet): RedirectResponse
    {
        $projet->delete();

        return redirect()->route('admin.projets.index')->with('status', 'Projet supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'status' => ['required', 'in:planifie,en_cours,termine'],
            'description' => ['required', 'string'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
