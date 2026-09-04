<?php

namespace App\Http\Controllers\Admin\Territoire;

use App\Http\Controllers\Controller;
use App\Models\Territoire\Arrondissement;
use App\Models\Territoire\Commune;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommuneController extends Controller
{
    public function index()
    {
        $communes = Commune::with('arrondissement')->orderBy('name')->get();

        return view('admin.territoire.communes.index', compact('communes'));
    }

    public function create()
    {
        $arrondissements = Arrondissement::orderBy('name')->get();
        $commune = new Commune;

        return view('admin.territoire.communes.form', compact('arrondissements', 'commune'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Commune::create($data);

        return redirect()->route('admin.territoire.communes.index')->with('status', 'Commune créée.');
    }

    public function edit(Commune $commune)
    {
        $arrondissements = Arrondissement::orderBy('name')->get();

        return view('admin.territoire.communes.form', compact('arrondissements', 'commune'));
    }

    public function update(Request $request, Commune $commune): RedirectResponse
    {
        $data = $commune->applyVerificationStamp($this->validated($request), $request->user());

        $commune->update($data);

        return redirect()->route('admin.territoire.communes.index')->with('status', 'Commune mise à jour.');
    }

    public function destroy(Commune $commune): RedirectResponse
    {
        $commune->delete();

        return redirect()->route('admin.territoire.communes.index')->with('status', 'Commune supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'arrondissement_id' => ['required', 'exists:arrondissements,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'description' => ['nullable', 'string'],
            'population' => ['nullable', 'integer', 'min:0'],
            'population_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
