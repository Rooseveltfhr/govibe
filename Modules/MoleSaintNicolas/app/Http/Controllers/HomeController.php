<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Territoire\Arrondissement;

class HomeController extends Controller
{
    public function index()
    {
        $arrondissement = Arrondissement::with('communes')->first();

        // "Toutes les photos" (brief client) : plafonné par précaution pour ne
        // pas charger un diaporama sans fin si la galerie grossit beaucoup.
        $photos = Photo::orderByDesc('created_at')->limit(30)->get();

        return view('home', compact('arrondissement', 'photos'));
    }
}
