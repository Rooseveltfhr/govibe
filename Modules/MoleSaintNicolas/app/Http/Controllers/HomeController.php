<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Etablissements\Establishment;
use App\Models\Event;
use App\Models\Histoire\HistoricalSite;
use App\Models\Photo;
use App\Models\Post;
use App\Models\Territoire\Arrondissement;

class HomeController extends Controller
{
    public function index()
    {
        $arrondissement = Arrondissement::with('communes')->first();
        $hotels = Establishment::type('hotel')->orderBy('name')->limit(3)->get();
        $restaurants = Establishment::whereIn('type', ['restaurant', 'bar'])->orderBy('name')->limit(3)->get();
        $sites = HistoricalSite::orderBy('name')->limit(3)->get();
        $posts = Post::published()->orderByDesc('published_at')->limit(3)->get();
        $activities = Activity::orderBy('title')->limit(3)->get();
        $events = Event::orderBy('starts_at')->limit(3)->get();
        $photos = Photo::orderByDesc('created_at')->limit(4)->get();

        return view('home', compact(
            'arrondissement', 'hotels', 'restaurants', 'sites', 'posts', 'activities', 'events', 'photos'
        ));
    }
}
