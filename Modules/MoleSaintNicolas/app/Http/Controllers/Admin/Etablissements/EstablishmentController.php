<?php

namespace App\Http\Controllers\Admin\Etablissements;

use App\Http\Controllers\Controller;
use App\Models\Etablissements\Establishment;
use App\Models\Territoire\SectionCommunale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EstablishmentController extends Controller
{
    public function index()
    {
        $establishments = Establishment::orderBy('type')->orderBy('name')->get();

        return view('admin.etablissements.index', compact('establishments'));
    }

    public function create()
    {
        $sections = SectionCommunale::orderBy('name')->get();
        $establishment = new Establishment;

        return view('admin.etablissements.form', compact('sections', 'establishment'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Establishment::create($data);

        return redirect()->route('admin.etablissements.index')->with('status', 'Établissement créé.');
    }

    public function edit(Establishment $etablissement)
    {
        $sections = SectionCommunale::orderBy('name')->get();

        return view('admin.etablissements.form', ['establishment' => $etablissement, 'sections' => $sections]);
    }

    public function update(Request $request, Establishment $etablissement): RedirectResponse
    {
        $data = $etablissement->applyVerificationStamp($this->validated($request), $request->user());

        $etablissement->update($data);

        return redirect()->route('admin.etablissements.index')->with('status', 'Établissement mis à jour.');
    }

    public function destroy(Establishment $etablissement): RedirectResponse
    {
        $etablissement->delete();

        return redirect()->route('admin.etablissements.index')->with('status', 'Établissement supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:hotel,restaurant,bar'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'description' => ['nullable', 'string'],
            'section_communale_id' => ['nullable', 'exists:sections_communales,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'price_range' => ['nullable', 'string', 'max:10'],
            'cuisine_type' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'string'],
            'opening_hours' => ['nullable', 'string'],
            'content_status' => ['required', 'in:verified,submitted,needs_review'],
            'source_note' => ['nullable', 'string'],
        ]);
    }
}
