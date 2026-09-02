<?php

namespace App\Http\Controllers\Admin\Territoire;

use App\Http\Controllers\Controller;
use App\Models\Territoire\Commune;
use App\Models\Territoire\SectionCommunale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectionCommunaleController extends Controller
{
    public function index()
    {
        $sections = SectionCommunale::with('commune')->orderBy('name')->get();

        return view('admin.territoire.sections.index', compact('sections'));
    }

    public function create()
    {
        $communes = Commune::orderBy('name')->get();
        $section = new SectionCommunale;

        return view('admin.territoire.sections.form', compact('communes', 'section'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        SectionCommunale::create($data);

        return redirect()->route('admin.territoire.sections.index')->with('status', 'Section communale créée.');
    }

    public function edit(SectionCommunale $section)
    {
        $communes = Commune::orderBy('name')->get();

        return view('admin.territoire.sections.form', compact('communes', 'section'));
    }

    public function update(Request $request, SectionCommunale $section): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['content_status'] === 'verified' && ! $section->isVerified()) {
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
        }

        $section->update($data);

        return redirect()->route('admin.territoire.sections.index')->with('status', 'Section communale mise à jour.');
    }

    public function destroy(SectionCommunale $section): RedirectResponse
    {
        $section->delete();

        return redirect()->route('admin.territoire.sections.index')->with('status', 'Section communale supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'commune_id' => ['required', 'exists:communes,id'],
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
