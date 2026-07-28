<?php

namespace App\Http\Controllers\ERP\Projects;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with(['client','manager','businessUnit']);
        if ($request->filled('search'))  $query->where('name','like',"%{$request->search}%");
        if ($request->filled('status'))  $query->where('status', $request->status);
        if ($request->filled('view') && $request->view === 'kanban') {
            $projects = $query->get()->groupBy('status');
            return view('erp.projects.kanban', compact('projects'));
        }
        $projects = $query->latest()->paginate(15)->withQueryString();
        $stats = [
            'total'     => Project::count(),
            'active'    => Project::where('status','active')->count(),
            'completed' => Project::where('status','completed')->count(),
            'on_hold'   => Project::where('status','on_hold')->count(),
        ];
        return view('erp.projects.index', compact('projects','stats'));
    }

    public function kanban()
    {
        $projects = Project::with(['client','manager'])->get()->groupBy('status');
        return view('erp.projects.kanban', compact('projects'));
    }

    public function create()
    {
        $clients       = Client::where('status','active')->get();
        $businessUnits = BusinessUnit::where('active',true)->get();
        $managers      = User::where('is_admin',true)->get();
        return view('erp.projects.create', compact('clients','businessUnits','managers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'client_id'        => 'nullable|exists:clients,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'status'           => 'required|in:planning,active,on_hold,completed,cancelled',
            'priority'         => 'required|in:low,medium,high,critical',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'budget'           => 'nullable|numeric|min:0',
            'manager_id'       => 'nullable|exists:users,id',
        ]);
        $validated['reference'] = 'PRJ-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
        $validated['progress']  = 0;

        $project = Project::create($validated);
        return redirect()->route('erp.projects.show', $project)->with('success', 'Projet créé avec succès.');
    }

    public function show(Project $project)
    {
        $project->load(['client','manager','businessUnit','tasks','members','milestones','comments.user']);
        return view('erp.projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $clients       = Client::where('status','active')->get();
        $businessUnits = BusinessUnit::where('active',true)->get();
        $managers      = User::where('is_admin',true)->get();
        return view('erp.projects.edit', compact('project','clients','businessUnits','managers'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'client_id'        => 'nullable|exists:clients,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'status'           => 'required|in:planning,active,on_hold,completed,cancelled',
            'priority'         => 'required|in:low,medium,high,critical',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date',
            'budget'           => 'nullable|numeric|min:0',
            'manager_id'       => 'nullable|exists:users,id',
            'progress'         => 'nullable|integer|min:0|max:100',
        ]);
        $project->update($validated);
        return redirect()->route('erp.projects.show', $project)->with('success', 'Projet mis à jour.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return redirect()->route('erp.projects.index')->with('success', 'Projet supprimé.');
    }
}
