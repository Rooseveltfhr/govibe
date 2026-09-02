<?php

namespace App\Http\Controllers;

use App\Models\Territoire\Arrondissement;

class HomeController extends Controller
{
    public function index()
    {
        $arrondissement = Arrondissement::with('communes')->first();

        return view('home', compact('arrondissement'));
    }
}
