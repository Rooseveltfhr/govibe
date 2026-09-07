<?php

namespace App\Http\Controllers;

use App\Models\Activity;

class ActivityController extends Controller
{
    public function index()
    {
        $activities = Activity::orderBy('title')->get();

        return view('explorer.index', compact('activities'));
    }

    public function show(string $slug)
    {
        $activity = Activity::where('slug', $slug)->firstOrFail();

        return view('explorer.show', compact('activity'));
    }
}
