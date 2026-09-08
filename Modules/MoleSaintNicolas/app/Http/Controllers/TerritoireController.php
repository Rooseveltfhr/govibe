<?php

namespace App\Http\Controllers;

use App\Models\Territoire\Arrondissement;
use App\Models\Territoire\Commune;
use App\Models\Territoire\SectionCommunale;

class TerritoireController extends Controller
{
    public function index()
    {
        $arrondissement = Arrondissement::with(['department', 'communes'])->first();

        return view('territoire.index', compact('arrondissement'));
    }

    public function commune(string $communeSlug)
    {
        $commune = Commune::where('slug', $communeSlug)
            ->with(['arrondissement', 'sectionsCommunales'])
            ->firstOrFail();

        return view('territoire.commune', compact('commune'));
    }

    public function section(string $communeSlug, string $sectionSlug)
    {
        $commune = Commune::where('slug', $communeSlug)->firstOrFail();

        $section = SectionCommunale::where('commune_id', $commune->id)
            ->where('slug', $sectionSlug)
            ->with('localites')
            ->firstOrFail();

        return view('territoire.section', compact('commune', 'section'));
    }
}
