<?php

namespace App\Http\Controllers;

use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('starts_at')->get();

        return view('evenements.index', compact('events'));
    }

    public function show(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        return view('evenements.show', compact('event'));
    }
}
