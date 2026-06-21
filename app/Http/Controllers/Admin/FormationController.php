<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Request;

class FormationController extends Controller
{
    public function index()
    {
        $formations = Formation::withCount('inscriptions')->latest()->get();
        return view('admin.formations.index', compact('formations'));
    }

    public function create()
    {
        return view('admin.formations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:255',
            'description'      => 'nullable|string',
            'date_debut'       => 'nullable|date',
            'date_fin'         => 'nullable|date|after_or_equal:date_debut',
            'lieu'             => 'nullable|string|max:255',
            'whatsapp_link'    => 'nullable|url|max:500',
            'max_participants' => 'required|integer|min:1',
            'active'           => 'boolean',
        ], [
            'nom.required'              => 'Le nom de la formation est obligatoire.',
            'max_participants.required' => 'Le nombre maximum de participants est obligatoire.',
            'whatsapp_link.url'         => 'Le lien WhatsApp doit être une URL valide.',
        ]);

        $validated['active'] = $request->boolean('active', true);

        Formation::create($validated);

        return redirect()->route('admin.formations.index')
            ->with('success', 'Formation créée avec succès.');
    }

    public function edit(Formation $formation)
    {
        return view('admin.formations.edit', compact('formation'));
    }

    public function update(Request $request, Formation $formation)
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:255',
            'description'      => 'nullable|string',
            'date_debut'       => 'nullable|date',
            'date_fin'         => 'nullable|date|after_or_equal:date_debut',
            'lieu'             => 'nullable|string|max:255',
            'whatsapp_link'    => 'nullable|url|max:500',
            'max_participants' => 'required|integer|min:1',
            'active'           => 'boolean',
        ]);

        $validated['active'] = $request->boolean('active');

        $formation->update($validated);

        return redirect()->route('admin.formations.index')
            ->with('success', 'Formation mise à jour avec succès.');
    }

    public function destroy(Formation $formation)
    {
        if ($formation->inscriptions()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer cette formation car elle contient des inscriptions.');
        }

        $formation->delete();
        return redirect()->route('admin.formations.index')
            ->with('success', 'Formation supprimée avec succès.');
    }
}
