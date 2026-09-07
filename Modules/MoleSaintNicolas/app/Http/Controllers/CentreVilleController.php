<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Territoire\Commune;

class CentreVilleController extends Controller
{
    public function index()
    {
        $page = Page::where('slug', 'centre-ville')->firstOrFail();
        $commune = Commune::where('slug', 'mole-saint-nicolas')->first();

        return view('centre-ville.index', compact('page', 'commune'));
    }
}
