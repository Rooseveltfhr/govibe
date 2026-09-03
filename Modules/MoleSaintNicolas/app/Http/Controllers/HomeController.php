<?php

namespace App\Http\Controllers;

use App\Models\Etablissements\Establishment;
use App\Models\Territoire\Arrondissement;

class HomeController extends Controller
{
    public function index()
    {
        $arrondissement = Arrondissement::with('communes')->first();
        $hotels = Establishment::type('hotel')->orderBy('name')->limit(3)->get();
        $restaurants = Establishment::whereIn('type', ['restaurant', 'bar'])->orderBy('name')->limit(3)->get();

        return view('home', compact('arrondissement', 'hotels', 'restaurants'));
    }
}
