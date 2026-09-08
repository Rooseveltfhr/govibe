<?php

namespace App\Http\Controllers;

use App\Models\CommunityProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityProjectController extends Controller
{
    public function index()
    {
        $projects = CommunityProject::orderByDesc('created_at')->get();

        return view('projets.index', compact('projects'));
    }

    public function show(string $slug)
    {
        $project = CommunityProject::where('slug', $slug)
            ->with('approvedComments')
            ->firstOrFail();

        return view('projets.show', compact('project'));
    }

    public function storeComment(Request $request, string $slug): RedirectResponse
    {
        $project = CommunityProject::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'author_name' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $project->comments()->create($data);

        return redirect()
            ->route('projets.show', $project->slug)
            ->with('status', 'Merci ! Votre commentaire sera visible après validation par l\'équipe.');
    }
}
