<?php

namespace App\Http\Controllers;

use App\Models\Etablissements\Establishment;
use App\Models\Histoire\HistoricalSite;
use App\Models\Territoire\Commune;

class CarteController extends Controller
{
    public function index()
    {
        $markers = collect()
            ->concat(Commune::whereNotNull('lat')->whereNotNull('lng')->get()->map(fn ($c) => [
                'lat' => (float) $c->lat,
                'lng' => (float) $c->lng,
                'label' => $c->name.' (commune)',
            ]))
            ->concat(HistoricalSite::whereNotNull('lat')->whereNotNull('lng')->get()->map(fn ($s) => [
                'lat' => (float) $s->lat,
                'lng' => (float) $s->lng,
                'label' => $s->name,
                'href' => route('lieux-historiques.show', $s->slug),
            ]))
            ->concat(Establishment::whereNotNull('lat')->whereNotNull('lng')->get()->map(fn ($e) => [
                'lat' => (float) $e->lat,
                'lng' => (float) $e->lng,
                'label' => $e->name,
                'href' => route($e->type === 'hotel' ? 'hotels.show' : 'restaurants.show', $e->slug),
            ]))
            ->values();

        return view('carte.index', compact('markers'));
    }
}
