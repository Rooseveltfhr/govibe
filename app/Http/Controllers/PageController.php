<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function about()
    {
        return view('about');
    }

    public function coworking()
    {
        return view('services.coworking');
    }

    public function startupLab()
    {
        return view('services.startup-lab');
    }

    public function academy()
    {
        return view('academy');
    }
}
