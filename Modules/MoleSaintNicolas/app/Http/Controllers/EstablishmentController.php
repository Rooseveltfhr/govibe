<?php

namespace App\Http\Controllers;

use App\Models\Etablissements\Establishment;

class EstablishmentController extends Controller
{
    public function hotels()
    {
        $establishments = Establishment::type('hotel')->orderBy('name')->get();

        return view('etablissements.index', [
            'establishments' => $establishments,
            'title' => 'Hôtels et hébergements',
            'typeSlug' => 'hotels',
        ]);
    }

    public function restaurants()
    {
        $establishments = Establishment::whereIn('type', ['restaurant', 'bar'])->orderBy('name')->get();

        return view('etablissements.index', [
            'establishments' => $establishments,
            'title' => 'Restaurants et bars',
            'typeSlug' => 'restaurants',
        ]);
    }

    public function showHotel(string $slug)
    {
        return $this->show($slug, 'hotels');
    }

    public function showRestaurant(string $slug)
    {
        return $this->show($slug, 'restaurants');
    }

    private function show(string $slug, string $typeSlug)
    {
        $establishment = Establishment::where('slug', $slug)->firstOrFail();

        return view('etablissements.show', compact('establishment', 'typeSlug'));
    }
}
